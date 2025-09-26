import { execFileSync } from 'node:child_process';

type MoveScenarioResult = {
    status: string;
    error: string | null;
    rows: Array<{
        id: number;
        parent_id: number | null;
        position: number;
    }>;
    operations: Array<{ type: string; action: string }>;
    executions: Array<{
        sql: string;
        params: Record<string, unknown>;
    }>;
};

const SQLSTATE_MISMATCH_ERROR =
    'SQLSTATE[HY093]: Invalid parameter number: number of bound variables does not match number of tokens';
const SQLSTATE_DUPLICATE_ENTRY_ERROR =
    "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '17-1' for key 'uq_definitions_parent_position'";

const runScenario = (scenario: string): MoveScenarioResult => {
    const output = execFileSync('php', ['server/tests/definitions_move_runner.php'], {
        input: JSON.stringify({ scenario }),
        encoding: 'utf-8',
    });

    return JSON.parse(output) as MoveScenarioResult;
};

describe('definitions_move integration SQL harness', () => {
    const normalise = (sql: string) => sql.replace(/\s+/g, ' ').trim();

    it('moves a node into a new parent and reorders without collisions', () => {
        const result = runScenario('move_to_new_parent');

        expect(result.status).toBe('ok');
        expect(result.error).toBeNull();
        expect(result.error).not.toBe(SQLSTATE_MISMATCH_ERROR);
        expect(result.error).not.toBe(SQLSTATE_DUPLICATE_ENTRY_ERROR);
        expect(result.operations).toEqual([
            { type: 'transaction', action: 'begin' },
            { type: 'transaction', action: 'commit' },
        ]);

        const queries = result.executions.map((entry) => ({
            sql: normalise(entry.sql),
            params: entry.params,
        }));

        const firstSelect = queries[0];
        expect(firstSelect.sql).toBe(
            'SELECT id, parent_id, title, position, meta, created_at, updated_at FROM definitions WHERE id = :id'
        );

        const lockQueries = queries.filter((entry) =>
            entry.sql === 'SELECT id FROM definitions WHERE parent_id <=> :parent ORDER BY position FOR UPDATE'
        );
        expect(lockQueries).toHaveLength(2);
        expect(lockQueries[0].params[':parent']).toBe(1);
        expect(lockQueries[1].params[':parent']).toBe(4);

        const parkingIndex = queries.findIndex(
            (entry) =>
                entry.sql === 'UPDATE definitions SET position = :position WHERE id = :id' &&
                typeof entry.params[':position'] === 'number' &&
                (entry.params[':position'] as number) > 1000
        );
        const parentUpdateIndex = queries.findIndex(
            (entry) => entry.sql === 'UPDATE definitions SET parent_id = :parent, position = :position WHERE id = :id'
        );
        expect(parkingIndex).toBeGreaterThan(-1);
        expect(parentUpdateIndex).toBeGreaterThan(-1);
        expect(parkingIndex).toBeLessThan(parentUpdateIndex);

        const bumpOrders = queries.filter((entry) =>
            entry.sql === 'UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent'
        );
        expect(bumpOrders).toHaveLength(2);
        expect(bumpOrders[0].params[':parent']).toBe(4);
        expect(bumpOrders[1].params[':parent']).toBe(1);

        const parentFour = result.rows.filter((row) => row.parent_id === 4);
        expect(parentFour.map((row) => [row.id, row.position])).toEqual([
            [5, 0],
            [3, 1],
        ]);

        const parentOne = result.rows.filter((row) => row.parent_id === 1);
        expect(parentOne.map((row) => [row.id, row.position])).toEqual([[2, 0]]);

        const combos = result.rows.map((row) => `${row.parent_id ?? 'root'}-${row.position}`);
        const uniqueCombos = new Set(combos);
        expect(uniqueCombos.size).toBe(combos.length);
    });

    it('reindexes siblings when moving within the same parent', () => {
        const result = runScenario('move_within_parent_down');

        expect(result.status).toBe('ok');
        expect(result.error).toBeNull();
        expect(result.error).not.toBe(SQLSTATE_MISMATCH_ERROR);
        expect(result.error).not.toBe(SQLSTATE_DUPLICATE_ENTRY_ERROR);

        const parentTen = result.rows.filter((row) => row.parent_id === 10);
        expect(parentTen.map((row) => [row.id, row.position])).toEqual([
            [12, 0],
            [11, 1],
            [13, 2],
        ]);

        const shiftQuery = result.executions.find(
            (entry) =>
                normalise(entry.sql) ===
                    'UPDATE definitions SET position = position + 1 WHERE parent_id <=> :parent AND position >= :position' &&
                entry.params[':parent'] === 10
        );
        expect(shiftQuery).toBeDefined();

        const combos = result.rows.map((row) => `${row.parent_id ?? 'root'}-${row.position}`);
        expect(new Set(combos).size).toBe(combos.length);
    });

    it('appends to the root level and preserves contiguous ordering', () => {
        const result = runScenario('move_to_root');

        expect(result.status).toBe('ok');
        expect(result.error).toBeNull();
        expect(result.error).not.toBe(SQLSTATE_MISMATCH_ERROR);
        expect(result.error).not.toBe(SQLSTATE_DUPLICATE_ENTRY_ERROR);

        const rootRows = result.rows.filter((row) => row.parent_id === null);
        expect(rootRows.map((row) => [row.id, row.position])).toEqual([
            [20, 0],
            [23, 1],
            [22, 2],
        ]);

        const bumpRoot = result.executions.filter(
            (entry) =>
                normalise(entry.sql) ===
                    'UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent' &&
                (entry.params[':parent'] === null || entry.params[':parent'] === '')
        );
        expect(bumpRoot).toHaveLength(1);

        const bumpOldParent = result.executions.filter(
            (entry) =>
                normalise(entry.sql) ===
                    'UPDATE definitions SET position = position + 1000000 WHERE parent_id <=> :parent' &&
                entry.params[':parent'] === 20
        );
        expect(bumpOldParent).toHaveLength(1);

        const combos = result.rows.map((row) => `${row.parent_id ?? 'root'}-${row.position}`);
        expect(new Set(combos).size).toBe(combos.length);
    });

    it('short-circuits without touching order when nothing changes', () => {
        const result = runScenario('no_op_same_slot');

        expect(result.status).toBe('ok');
        expect(result.error).toBeNull();
        expect(result.error).not.toBe(SQLSTATE_MISMATCH_ERROR);
        expect(result.error).not.toBe(SQLSTATE_DUPLICATE_ENTRY_ERROR);
        expect(result.operations).toEqual([
            { type: 'transaction', action: 'begin' },
            { type: 'transaction', action: 'commit' },
        ]);

        const updateQueries = result.executions.filter((entry) =>
            normalise(entry.sql).startsWith('UPDATE definitions SET')
        );
        expect(updateQueries).toHaveLength(0);

        const parentThirty = result.rows.filter((row) => row.parent_id === 30);
        expect(parentThirty.map((row) => [row.id, row.position])).toEqual([
            [31, 0],
            [32, 1],
        ]);
    });
});
