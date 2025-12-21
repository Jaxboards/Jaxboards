class Sound {
    private soundCache: Record<string, HTMLAudioElement> = {};

    load(title: string, file: string, autoplay: boolean) {
        let audio = this.soundCache[title];

        if (audio) {
            if (autoplay) {
                this.play(title);
            }

            // do not load again
            return;
        }

        audio = new Audio();
        this.soundCache[title] = audio;
        audio.autoplay = !!autoplay;
        audio.src = file;
    }

    play(title: string) {
        this.soundCache[title].play();
    }

    loadAndPlay(title: string, file: string) {
        this.load(title, file, true);
    }
}

// Sound is a singleton
export default new Sound();
