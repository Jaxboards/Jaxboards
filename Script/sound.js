class Sound {
  constructor() {
    this.soundCache = {};
  }

  load(title, file, autoplay) {
    const audio = new Audio();
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
