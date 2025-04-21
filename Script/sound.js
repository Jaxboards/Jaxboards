class Sound {
  constructor() {
    this.soundCache = {};
  }

  load(title, file, autoplay) {
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

  play(title) {
    this.soundCache[title].play();
  }

  loadAndPlay(title, file) {
    this.load(title, file, true);
  }
}

// Sound is a singleton
export default new Sound();
