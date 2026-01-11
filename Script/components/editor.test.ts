import Editor from './editor';

describe('Editor', () => {
    test('cmd("bold") wraps selection in plaintext mode', () => {
        const textarea = document.createElement('textarea');
        textarea.value = 'hello world';
        textarea.selectionStart = 6;
        textarea.selectionEnd = 11;

        const editor = new Editor(textarea as HTMLTextAreaElement);
        editor.htmlMode = false;

        editor.cmd('bold');

        expect(textarea.value).toBe('hello [b]world[/b]');
    });

    test('switchMode(true) hides textarea and shows iframe', () => {
        const textarea = document.createElement('textarea');
        textarea.value = '[b]bold[/b]';

        const editor = new Editor(textarea as HTMLTextAreaElement);

        // Ensure starting state
        textarea.style.display = '';
        editor.iframe.style.display = 'none';

        editor.switchMode(true);

        expect(editor.htmlMode).toBe(true);
        expect(textarea.style.display).toBe('none');
        expect(editor.iframe.style.display).toBe('');
    });

    test('in iframe mode cmd executes document command and focuses window', () => {
        const textarea = document.createElement('textarea');
        textarea.value = 'hello';

        const editor = new Editor(textarea as HTMLTextAreaElement);

        Object.defineProperty(editor.iframe, 'contentWindow', {
            value: window,
            configurable: true,
        });

        editor.htmlMode = true;

        const execMock = jest.fn(() => true);
        (window.document as any).execCommand = execMock;
        const focusMock = jest.fn();
        (window as any).focus = focusMock;

        editor.cmd('bold');

        expect(execMock).toHaveBeenCalledWith('bold', false, undefined);
        expect(focusMock).toHaveBeenCalled();
    });

    test('getSelection returns window selection string in html mode', () => {
        const textarea = document.createElement('textarea');
        const editor = new Editor(textarea as HTMLTextAreaElement);
        Object.defineProperty(editor.iframe, 'contentWindow', {
            value: window,
            configurable: true,
        });

        editor.htmlMode = true;

        const selMock = { toString: () => 'my selection' };
        const getSelectionSpy = jest
            .spyOn(window as any, 'getSelection')
            .mockImplementation(() => selMock);

        expect(editor.getSelection()).toBe('my selection');

        getSelectionSpy.mockRestore();
    });

    test('setSource updates iframe document body when present', () => {
        const textarea = document.createElement('textarea');
        const editor = new Editor(textarea as HTMLTextAreaElement);
        const fakeDoc = document.implementation.createHTMLDocument('fake');
        const fakeWindow = { document: fakeDoc } as unknown as Window;
        Object.defineProperty(editor.iframe, 'contentWindow', {
            value: fakeWindow,
            configurable: true,
        });

        editor.setSource('<div>testing</div>');

        const doc =
            editor.doc ?? (editor.iframe.contentWindow as any)?.document;
        expect(doc?.body.innerHTML).toBe('<div>testing</div>');
    });
});
