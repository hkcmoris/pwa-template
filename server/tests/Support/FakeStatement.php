<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use PDOException;

class FakeStatement
{
    private FakePDO $pdo;

    private string $query;

    public string $queryString;

    /**
     * @var array<int,string>
     */
    private array $placeholders;

    /**
     * @var array<string,mixed>
     */
    private array $params = [];

    /**
     * @var array<int,array<string|int,mixed>>
     */
    private array $results = [];

    private int $cursor = 0;

    public function __construct(FakePDO $pdo, string $query)
    {
        $this->pdo = $pdo;
        $this->query = $query;
        $this->queryString = $query;
        $this->placeholders = $this->extractPlaceholders($query);
    }

    public function bindValue(string $param, $value, int $type = PDO::PARAM_STR): bool
    {
        if ($type === PDO::PARAM_NULL) {
            $this->params[$param] = null;
        } else {
            $this->params[$param] = $value;
        }
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $this->params[$key] = $value;
            }
        }

        $this->assertPlaceholderMatch();
        $this->results = $this->pdo->executeQuery($this->query, $this->params);
        $this->cursor = 0;
        return true;
    }

    /**
     * @return array<string|int,mixed>|false
     */
    public function fetch()
    {
        if (!isset($this->results[$this->cursor])) {
            return false;
        }
        return $this->results[$this->cursor++];
    }

    /**
     * @return mixed
     */
    public function fetchColumn(int $column = 0)
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        return reset($row);
    }

    /**
     * @param int $mode
     * @return array<int,mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            $values = [];
            foreach ($this->results as $row) {
                $values[] = reset($row);
            }
            return $values;
        }
        return $this->results;
    }

    public function closeCursor(): bool
    {
        $this->results = [];
        $this->cursor = 0;
        return true;
    }

    /**
     * @return array<int,string>
     */
    private function extractPlaceholders(string $query): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $query, $matches);
        $placeholders = [];
        foreach ($matches[0] ?? [] as $placeholder) {
            if (!in_array($placeholder, $placeholders, true)) {
                $placeholders[] = $placeholder;
            }
        }

        return $placeholders;
    }

    private function assertPlaceholderMatch(): void
    {
        $boundKeys = array_keys($this->params);
        sort($boundKeys);
        $expected = $this->placeholders;
        sort($expected);
        if ($boundKeys === $expected) {
            return;
        }

        throw new PDOException('SQLSTATE[HY093]: Invalid parameter number: number of bound variables does not match number of tokens');
    }
}
