export { };

type globalsettings = {
    can_im: boolean,
    groupid: number,
    sound_im: boolean,
    userid: number,
    username: string,
    wysiwyg: boolean,
}

declare global {
    const globalsettings: globalsettings
}
