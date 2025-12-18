const registry = new Map<string, WeakMap<HTMLElement, object>>();

export class Component<T extends HTMLElement> {
    protected element: T;

    constructor(element: T) {
        this.element = element;
    }
}

/**
 * Registers a list of HTMLElements to their Component instances.
 *
 * This also prevents the same HTMLElement from having multiple of the same component instance.
 *
 * @param className
 * @param nodeList
 * @param ComponentClass
 */
export default function register<
    T extends HTMLElement,
    TT extends typeof Component<T>,
>(className: string, nodeList: NodeListOf<T>, ComponentClass: TT) {
    let instances = registry.get(className);

    if (!instances) {
        instances = new WeakMap();
        registry.set(className, instances);
    }

    nodeList.forEach((element) => {
        if (instances.has(element)) {
            return;
        }

        instances.set(element, new ComponentClass(element));
    });
}
