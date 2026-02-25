class Sound {
  private soundCache: Record<string, HTMLAudioElement> = {};

  getFilePath(title: string) {
    return `/assets/sounds/${title}.mp3`;
  }

  load(title: string, autoplay = false) {
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
    audio.src = this.getFilePath(title);
  }

  loadAndPlay(title: string) {
    this.load(title, true);
  }

  play(title: string) {
    void this.soundCache[title]?.play();
  }
}

// Sound is a singleton
export default new Sound();
