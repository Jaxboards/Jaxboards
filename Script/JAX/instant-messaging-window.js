/* global RUN, globalsettings */

export default function IMWindow(uid, uname) {
    if (!globalsettings.can_im) {
        // eslint-disable-next-line no-alert
        alert('You do not have permission to use this feature.');
    } else {
        RUN.stream.commands.im([uid, uname, false]);
    }
}
