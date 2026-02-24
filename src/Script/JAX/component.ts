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
  if (!registry.has(className)) {
    registry.set(className, new WeakMap());
  }

  nodeList.forEach(function registerLoop(element) {
    if (registry.get(className)?.has(element)) {
      return;
    }

    registry.get(className)?.set(element, new ComponentClass(element));
  });
}
