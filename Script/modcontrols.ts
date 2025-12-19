import gracefulDegrade from './JAX/graceful-degrade';
import Commands from './RUN/commands';

function postIDs(strPIDs: string) {
    const pids = strPIDs ? strPIDs.split(',') : [];
    const pluralPosts = pids.length === 1 ? '' : 's';
    const andPosts = pids.length ? ' and <br>' : '';
    return [pids, pluralPosts, andPosts] as const;
}

function threadIDs(strTIDs: string) {
    const tids = strTIDs ? strTIDs.split(',') : [];
    const tl = tids ? tids.length : 0;
    const pluralThreads = tl === 1 ? '' : 's';
    return [tids, pluralThreads] as const;
}

export default class ModControls {
    private whichone = 0;

    private boundCheckLocation;

    private pids: string[] = [];

    private tids: string[] = [];

    private modb?: HTMLDivElement;

    constructor() {
        this.boundCheckLocation = () => this.checkLocation();

        Object.assign(Commands, {
            /**
             * @param {[string,string]} param0
             */
            modcontrols_postsync: (postIds: string, threadIds: string) => {
                const [pids, pluralPosts, andPosts] = postIDs(postIds);
                const [tids, pluralThreads] = threadIDs(threadIds);

                this.tids = tids;
                this.pids = pids;

                if (!tids.length && !pids.length) {
                    this.destroyModControls();

                    return;
                }

                const topicOptions = tids.length
                    ? `
                    <select name='dot'>
                        <option value='delete'>Delete</option>
                        <option value='merge'>Merge</option>
                        <option value='move'>Move</option>
                        <option value='pin'>Pin</option>
                        <option value='unpin'>Unpin</option>
                        <option value='lock'>Lock</option>
                        <option value='unlock'>Unlock</option>
                    </select> &nbsp; &nbsp;
                    <strong>${tids.length}</strong> topic${pluralThreads}${andPosts}`
                    : '';

                const postOptions = pids.length
                    ? `
                    <select name='dop'>
                        <option value='delete'>Delete</option>
                        <option value='move'>Move</option>
                    </select> &nbsp; &nbsp;
                    <strong>${pids.length}</strong> post${pluralPosts}`
                    : '';
                const spacing =
                    pids.length && tids.length ? '<br>' : ' &nbsp; &nbsp; ';

                const html = `
                <form method='post' data-ajax-form='true'>
                    <input type='hidden' name='act' value='modcontrols'>
                    ${topicOptions}
                    ${postOptions}
                    ${spacing}
                    <input type='submit' value='Go'>
                    <input
                        name='cancel' type='submit'
                        onclick='this.form.submitButton=this;' value='Cancel'>
                </form>`;

                this.createModControls(html);
            },

            modcontrols_move: (whichone: number) => {
                this.whichone = whichone;
                globalThis.addEventListener(
                    'pushstate',
                    this.boundCheckLocation,
                );
                this.createModControls(
                    `Ok, now browse to the ${
                        this.whichone ? 'topic' : 'forum'
                    } you want to move the ${
                        this.whichone
                            ? `${this.pids.length} posts`
                            : `${this.tids.length} topics`
                    } to...`,
                );
            },

            modcontrols_clearbox: () => {
                this.destroyModControls();
            },
        });
    }

    checkLocation() {
        const { whichone } = this;
        const regex = whichone ? /topic\/(\d+)/ : /forum\/(\d+)/;
        const locationMatch = document.location.toString().match(regex);
        if (locationMatch) {
            this.moveto(Number(locationMatch[1]));
        } else {
            Commands.modcontrols_move();
        }
    }

    moveto(id: number) {
        const { whichone } = this;
        this.createModControls(
            `<form method="post" data-ajax-form="true">
                move ${whichone ? 'posts' : 'topics'} here?
            <input type="hidden" name="act" value="modcontrols">
            <input type="hidden"
                name="${whichone ? 'dop' : 'dot'}"
                value="moveto">
            <input type="hidden" name="id" value="${id}">
            <input type="submit" value="Yes">
            <input type="submit" name="cancel" value="Cancel" onclick="this.form.submitButton=this">
            </form>`,
        );
    }

    createModControls(html: string) {
        this.modb = this.modb || document.querySelector('#modbox') || undefined;

        if (!this.modb) {
            this.modb = document.createElement('div');
            this.modb.id = 'modbox';
            document.body.appendChild(this.modb);
        }
        this.modb.style.display = 'block';
        this.modb.innerHTML = html;
        gracefulDegrade(this.modb);
    }

    destroyModControls() {
        globalThis.removeEventListener('pushstate', this.boundCheckLocation);
        if (this.modb) {
            this.modb.innerHTML = '';
            this.modb.style.display = 'none';
        }
    }

    // eslint-disable-next-line class-methods-use-this
    togbutton(button: HTMLButtonElement) {
        button.classList.toggle('selected');
    }
}
