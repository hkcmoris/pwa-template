import {
    isDescendantPath,
    setupDragAndDrop,
} from '../definitions-tree';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

type AjaxFn = Parameters<typeof setupDragAndDrop>[2]['ajax'];
type HtmxMock = { ajax: AjaxFn };

describe('isDescendantPath', () => {
    it('detects descendants correctly', () => {
        expect(isDescendantPath('1', '1')).toBe(true);
        expect(isDescendantPath('1', '1/2')).toBe(true);
        expect(isDescendantPath('1/2', '1/2/3')).toBe(true);
        expect(isDescendantPath('1/2', '1/20')).toBe(false);
        expect(isDescendantPath('1/2', '1/2-1')).toBe(false);
        expect(isDescendantPath('', '1/2')).toBe(false);
    });
});

type NodeParts = {
    item: HTMLLIElement;
    node: HTMLDivElement;
    list: HTMLUListElement;
};

const createItem = (
    id: string,
    parent: string,
    position: string,
    path: string
): NodeParts => {
    const item = document.createElement('li');
    item.className = 'definition-item';
    item.dataset.id = id;
    item.dataset.parent = parent;
    item.dataset.position = position;
    item.dataset.path = path;

    const node = document.createElement('div');
    node.className = 'definition-node';
    item.appendChild(node);

    const actions = document.createElement('div');
    actions.className = 'definition-actions';
    node.appendChild(actions);

    const list = document.createElement('ul');
    item.appendChild(list);

    return { item, node, list };
};

describe('setupDragAndDrop', () => {
    let ajaxMock: jest.MockedFunction<AjaxFn>;
    let htmxMock: HtmxMock;

    beforeEach(() => {
        document.body.innerHTML = '';
        ajaxMock = jest.fn<AjaxFn>();
        ajaxMock.mockImplementation(() => ({} as XMLHttpRequest));
        htmxMock = { ajax: ajaxMock };
    });

    const dispatchDragStart = (node: HTMLElement) => {
        const dragStart = new Event('dragstart', { bubbles: true, cancelable: true });
        node.dispatchEvent(dragStart);
    };

    const dispatchDragOver = (
        node: HTMLElement,
        clientY: number,
        bounding: DOMRect
    ) => {
        node.parentElement?.classList.remove('definition-item--drop-before');
        node.parentElement?.classList.remove('definition-item--drop-after');
        node.parentElement?.classList.remove('definition-item--drop-inside');

        const dragOver = new Event('dragover', { bubbles: true, cancelable: true });
        Object.defineProperty(dragOver, 'clientY', { value: clientY });
        jest
            .spyOn(node.closest<HTMLElement>('.definition-item')!, 'getBoundingClientRect')
            .mockReturnValue(bounding);
        const preventSpy = jest.spyOn(dragOver, 'preventDefault');
        node.dispatchEvent(dragOver);
        return preventSpy;
    };

    const dispatchDrop = (node: HTMLElement) => {
        const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
        const preventSpy = jest.spyOn(dropEvent, 'preventDefault');
        node.dispatchEvent(dropEvent);
        return preventSpy;
    };

    it('sends a move request when dropping inside another node', () => {
        const root = document.createElement('div');
        root.id = 'definitions-list';
        const list = document.createElement('ul');
        list.className = 'definition-tree';
        root.appendChild(list);

        const parent = createItem('1', '', '0', '1');
        const child = createItem('2', '1', '0', '1/2');
        parent.list.appendChild(child.item);
        list.appendChild(parent.item);

        const target = createItem('3', '', '1', '3');
        list.appendChild(target.item);

        document.body.appendChild(root);

        setupDragAndDrop(root, '', htmxMock);

        dispatchDragStart(child.node);

        const bounding = {
            top: 0,
            height: 90,
            bottom: 90,
            left: 0,
            right: 0,
            width: 120,
            x: 0,
            y: 0,
            toJSON: () => '',
        } as DOMRect;

        const preventOver = dispatchDragOver(target.node, 45, bounding);
        expect(preventOver).toHaveBeenCalled();

        const preventDrop = dispatchDrop(target.node);
        expect(preventDrop).toHaveBeenCalled();

        expect(ajaxMock).toHaveBeenCalledTimes(1);
        expect(ajaxMock.mock.calls[0][0]).toBe('POST');
        expect(ajaxMock.mock.calls[0][1]).toBe('/editor/definitions-move');
        expect(ajaxMock.mock.calls[0][2]).toEqual({
            source: '#definitions-list',
            values: { id: '2', parent_id: '3', position: '0' },
            target: '#definitions-list',
            swap: 'outerHTML',
            select: '#definitions-list',
        });
    });

    it('does not send a request when dropping into the same position', () => {
        const root = document.createElement('div');
        root.id = 'definitions-list';
        const list = document.createElement('ul');
        list.className = 'definition-tree';
        root.appendChild(list);

        const first = createItem('1', '', '0', '1');
        const second = createItem('2', '', '1', '2');
        list.appendChild(first.item);
        list.appendChild(second.item);

        document.body.appendChild(root);

        setupDragAndDrop(root, '', htmxMock);

        dispatchDragStart(second.node);

        const bounding = {
            top: 0,
            height: 60,
            bottom: 60,
            left: 0,
            right: 0,
            width: 120,
            x: 0,
            y: 0,
            toJSON: () => '',
        } as DOMRect;

        dispatchDragOver(first.node, 55, bounding);
        dispatchDrop(first.node);

        expect(ajaxMock).not.toHaveBeenCalled();
    });
});
