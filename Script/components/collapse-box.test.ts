import CollapseBox from './collapse-box';

test('collapse box', function () {
    document.body.innerHTML = `
        <div class="collapse-box">
            <button class="collapse-button">
            <div class="collapse-content">
            Hello world
            </div>
        </div>
    `;

    CollapseBox.hydrate(document.body);

    const collapseContent =
        document.querySelector<HTMLDivElement>('.collapse-content');

    expect(collapseContent?.style.height).toBe('');

    document.querySelector<HTMLButtonElement>('.collapse-button')?.click();

    expect(collapseContent?.style.height).toBe('0px');
});
