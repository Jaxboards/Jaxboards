import gracefulDegrade from './JAX/graceful-degrade';
import Commands from './RUN/commands';

function postIDs(strPIDs: string) {
    const pids = strPIDs ? strPIDs.split(',') : [];
    const pl = pids ? pids.length : 0;
    const pluralPosts = pids.length === 1 ? '' : 's';
    const andPosts = pl ? ' and <br />' : '';
    return [pids, pl, pluralPosts, andPosts];
}

function threadIDs(strTIDs: string) {
    const tids = strTIDs ? strTIDs.split(',') : [];
    const tl = tids ? tids.length : 0;
    const pluralThreads = tl === 1 ? '' : 's';
    return [tids, tl, pluralThreads];
}

export default class ModControls {
    constructor() {
        Object.assign(Commands, {
            /**
             * @param {[string,string]} param0
             */
            modcontrols_postsync: (postIds: string, threadIds: string) => {
                const [pids, pl, pluralPosts, andPosts] = postIDs(postIds);
                const [tids, tl, pluralThreads] = threadIDs(threadIds);
                const html =
                    `${
                        "<form method='post' data-ajax-form='true'>" +
                        "<input type='hidden' name='act' value='modcontrols' />"
                    }${
                        tl
                            ? `${
                                  "<select name='dot'>" +
                                  "<option value='delete'>Delete</option>" +
                                  "<option value='merge'>Merge</option>" +
                                  "<option value='move'>Move</option>" +
                                  "<option value='pin'>Pin</option>" +
                                  "<option value='unpin'>Unpin</option>" +
                                  "<option value='lock'>Lock</option>" +
                                  "<option value='unlock'>Unlock</option>" +
                                  '</select>' +
                                  '&nbsp; &nbsp; <strong>'
                              }${tl}</strong> topic${pluralThreads}${andPosts}`
                            : ''
                    }${
                        pl
                            ? `${
                                  "<select name='dop'>" +
                                  "<option value='delete'>Delete</option>" +
                                  "<option value='move'>Move</option>" +
                                  '</select> &nbsp; &nbsp; <strong>'
                              }${pl}</strong> post${pluralPosts}`
                            : ''
                    }${
                        pl && tl ? '<br />' : ' &nbsp; &nbsp; '
                    }<input type='submit' value='Go' /> ` +
                    "<input name='cancel' type='submit' " +
                    "onclick='this.form.submitButton=this;' value='Cancel' /></form>";
                Object.assign(this, {
                    tids,
                    tidl: tl,
                    pids,
                    pidl: pl,
                });
                if (tl || pl) this.createModControls(html);
                else this.destroyModControls();
            },

            modcontrols_move: (act: string) => {
                const whichone = Number.parseInt(act || this.whichone, 10);
                this.whichone = whichone;
                globalThis.addEventListener(
                    'pushstate',
                    this.boundCheckLocation,
                );
                this.createModControls(
                    `Ok, now browse to the ${
                        whichone ? 'topic' : 'forum'
                    } you want to move the ${
                        whichone ? `${this.pidl} posts` : `${this.tidl} topics`
                    } to...`,
                );
            },

            modcontrols_clearbox: () => {
                this.destroyModControls();
            },
        });

        this.boundCheckLocation = () => this.checkLocation();
    }

    checkLocation() {
        const { whichone } = this;
        const regex = whichone ? /topic\/(\d+)/ : /forum\/(\d+)/;
        const locationMatch = document.location.toString().match(regex);
        if (locationMatch) {
            this.moveto(locationMatch[1]);
        } else {
            Commands.modcontrols_move();
        }
    }

    moveto(id: number) {
        const { whichone } = this;
        this.createModControls(
            `<form method="post" data-ajax-form="true">move ${
                whichone ? 'posts' : 'topics'
            } here? <input type="hidden" name="act" value="modcontrols" />` +
                `<input type="hidden" name="${
                    whichone ? 'dop' : 'dot'
                }" value="moveto" /><input type="hidden" name="id" value="${id}" /><input type="submit" value="Yes" />` +
                '<input type="submit" name="cancel" value="Cancel" ' +
                'onclick="this.form.submitButton=this" /></form>',
        );
    }

    createModControls(html: string) {
        let modb = this.modb || document.querySelector('#modbox');
        if (!this.modb) {
            modb = document.createElement('div');
            modb.id = 'modbox';
            document.body.appendChild(modb);
        }
        modb.style.display = 'block';
        modb.innerHTML = html;
        gracefulDegrade(modb);
        this.modb = modb;
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
