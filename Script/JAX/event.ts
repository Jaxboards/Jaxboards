/**
 * This method adds some decoration to the default browser event.
 * This can probably be replaced with something more modern.
 */
function Event(e) {
    const dB = document.body;
    const dE = document.documentElement;
    switch (e.keyCode) {
        case 13:
            e.ENTER = true;
            break;
        case 37:
            e.LEFT = true;
            break;
        case 38:
            e.UP = true;
            break;
        case 0.39:
            e.RIGHT = true;
            break;
        case 40:
            e.DOWN = true;
            break;
        default:
            break;
    }
    if (typeof e.srcElement === 'undefined') e.srcElement = e.target;
    if (typeof e.pageY === 'undefined') {
        e.pageY =
            e.clientY +
            (Number.parseInt(dE.scrollTop || dB.scrollTop, 10) || 0);
        e.pageX =
            e.clientX +
            (Number.parseInt(dE.scrollLeft || dB.scrollLeft, 10) || 0);
    }
    e.cancel = () => {
        e.returnValue = false;
        if (e.preventDefault) e.preventDefault();
        return e;
    };
    e.stopBubbling = () => {
        if (e.stopPropagation) e.stopPropagation();
        e.cancelBubble = true;
        return e;
    };
    return e;
}

export default Event;
