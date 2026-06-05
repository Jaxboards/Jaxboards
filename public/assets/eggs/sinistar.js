/* eslint-disable */
(function () {
  const firstSinistar = [-237, -8];
  const sinistarSize = [48, 48];

  class Sinistar {
    frameNumber = 0;
    chaser = document.createElement("div");
    audio = document.createElement("audio");
    mouse = [0, 0];
    maxSpeed = 10;
    maxAcceleration = 1;
    dead = false;
    mouseListener = (e) => {
      this.mouse = [e.clientX, e.clientY];
    };

    constructor() {
      this.captureMouse();
    }

    frame() {
      this.animateChaser();
      this.frameNumber++;
    }

    captureMouse() {
      document.body.addEventListener("mousemove", this.mouseListener);
    }

    initializeChaser() {
      Object.assign(this.chaser.style, {
        position: "fixed",
        top: "0",
        left: "0",
        background: 'url("/assets/eggs/sinistar/spritesheet.png")',
        height: "50px",
        width: "50px",
        scale: "200%",
        transformOrigin: "0 0",
        transform: `translate(-${sinistarSize[0] / 2}px, -${sinistarSize[1] / 2}px)`,
        backgroundPositionX: `${firstSinistar[0]}px`,
        backgroundPositionY: `${firstSinistar[1]}px`,
      });
      Object.assign(this.chaser.dataset, {
        velocityX: "5",
        velocityY: "5",
      });
      window.document.body.appendChild(this.chaser);
      this.playChase();
      this.timer = setInterval(() => this.frame(), 1000 / 60);
    }

    playIntro() {
      this.audio.src = "/assets/eggs/sinistar/i-am-sinistar.mp3#t=0,4";
      this.audio.play();
      setTimeout(() => this.initializeChaser(), 6000);
    }

    playChase() {
      this.audio.src = "/assets/eggs/sinistar/i-am-sinistar.mp3#t=4,10";
      this.audio.play();
    }

    playDeath() {
      this.dead = true;
      Object.assign(this.chaser.dataset, { velocityX: 0, velocityY: 0 });
      Object.assign(this.chaser.style, {
        left: `${this.mouse[0]}px`,
        top: `${this.mouse[1]}px`,
      });

      document.body.removeEventListener("mousemove", this.mouseListener);
      document.body.style.cursor = "none";

      this.audio.src = "/assets/eggs/sinistar/i-am-sinistar.mp3#t=10";
      this.audio.play();
      setTimeout(() => {
        this.destroy();
      }, 4000);
    }

    animateChaser() {
      const spriteFrame = this.dead
        ? (Math.floor(this.frameNumber / 5) % 3) + 6
        : Math.floor(this.frameNumber / 5) % 6;
      const mouth = Math.floor(this.frameNumber / 10) % 3;
      const offsetFix = spriteFrame > 2 ? 0 : -1;

      const position = [
        parseInt(this.chaser.style.left) || 0,
        parseInt(this.chaser.style.top) || 0,
      ];

      let velocity = [
        Number(this.chaser.dataset.velocityX) || 0,
        Number(this.chaser.dataset.velocityY) || 0,
      ];

      const distance = [
        this.mouse[0] - position[0],
        this.mouse[1] - position[1],
      ];

      velocity[0] += Math.sign(distance[0]) / 2;
      velocity[1] += Math.sign(distance[1]) / 2;

      if (
        Math.sqrt(distance[0] ** 2 + distance[1] ** 2) < sinistarSize[0] &&
        !this.dead
      ) {
        this.playDeath();
        return;
      }

      // clamp max velocity
      velocity = velocity.map((speed) =>
        Math.abs(speed) > this.maxSpeed
          ? Math.sign(speed) * this.maxSpeed
          : speed,
      );

      position[0] += velocity[0] || 0;
      position[1] += velocity[1] || 0;

      Object.assign(this.chaser.style, {
        left: `${position[0]}px`,
        top: `${position[1]}px`,
        backgroundPositionX: `${firstSinistar[0] - spriteFrame * 53 + offsetFix}px`,
        backgroundPositionY: `${firstSinistar[1] - mouth * 56}px`,
      });
      Object.assign(this.chaser.dataset, {
        velocityX: velocity[0],
        velocityY: velocity[1],
      });
    }

    destroy() {
      document.body.style.cursor = "";
      this.chaser.remove();
      clearInterval(this.timer);
    }
  }

  const sinistar = new Sinistar();
  sinistar.playIntro();
})();
