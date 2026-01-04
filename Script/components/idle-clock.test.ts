import IdleClock from './idle-clock';

test('idle clock', function () {
    const twoThirtyAM = 1767512040;
    document.body.innerHTML = `<div class="idle lastAction${twoThirtyAM}">Sean</div>`;

    IdleClock.hydrate(document.body);

    expect(document.querySelector('.idle')?.innerHTML).toBe('🕝Sean');
});
