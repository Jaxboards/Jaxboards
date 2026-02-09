const rainbow = [
  "#F00",
  "#FF7F00",
  "#FF0",
  "#0F0",
  "#00F",
  "#4B0082",
  "#9400D3",
];

// Creating snowflakes
function generateSnow(snowDensity = 200) {
  let snowWrapper = document.querySelector<HTMLDivElement>("#snow");
  if (snowWrapper === null) {
    snowWrapper = document.createElement("div");
    snowWrapper.id = "snow";
    Object.assign(snowWrapper.style, {
      position: "fixed",
      top: 0,
      left: 0,
      zIndex: 1,
    });

    document.body.prepend(snowWrapper);
  }
  snowWrapper.innerHTML = "";
  for (let i = 1; i <= snowDensity; i += 1) {
    const board = document.createElement("div");
    board.className = "snowflake";
    snowWrapper.appendChild(board);
  }
}

function getOrCreateCSSElement() {
  let cssElement = document.getElementById("snow-css");
  if (cssElement) return cssElement;

  cssElement = document.createElement("style");
  cssElement.id = "snow-css";
  document.head.appendChild(cssElement);
  return cssElement;
}

// Append style for each snowflake to the head
function addCSS(rule: string) {
  const cssElement = getOrCreateCSSElement();
  cssElement.innerHTML = rule;
  document.head.appendChild(cssElement);
}

// Math
function randomInt(value = 100) {
  return Math.floor(Math.random() * value) + 1;
}

function randomIntRange(min: number, max: number) {
  const cmin = Math.ceil(min);
  const fmax = Math.floor(max);
  return Math.floor(Math.random() * (fmax - cmin + 1)) + cmin;
}

function getRandomArbitrary(min: number, max: number) {
  return Math.random() * (max - min) + min;
}

// Create style for snowflake
function generateSnowCSS(snowDensity = 200, useColors = false) {
  const bodyHeightPx = document.body.offsetHeight;
  const pageHeightVh = (100 * bodyHeightPx) / window.innerHeight;

  let rule = `
    .snowflake {
        position: absolute;
        width: 10px;
        height: 10px;
        /* Workaround for Chromium's selective color inversion */
        border-radius: 50%;
    }
  `;

  for (let i = 1; i <= snowDensity; i += 1) {
    const randomX = Math.random() * 100; // vw
    const randomOffset = Math.random() * 10; // vw;
    const randomXEnd = randomX + randomOffset;
    const randomXEndYoyo = randomX + randomOffset / 2;
    const randomYoyoTime = getRandomArbitrary(0.3, 0.8);
    const randomYoyoY = randomYoyoTime * pageHeightVh; // vh
    const randomScale = Math.random();
    const fallDuration = randomIntRange(10, (pageHeightVh / 10) * 3); // s
    const fallDelay = randomInt((pageHeightVh / 10) * 3) * -1; // s
    const opacity = Math.random();

    const color = useColors
      ? rainbow[Math.floor(Math.random() * rainbow.length)]
      : "white";

    rule += `
      .snowflake:nth-child(${i}) {
        opacity: ${opacity};
        background: linear-gradient(${color}, ${color});
        filter: drop-shadow(0 0 10px ${color});
        transform: translate(${randomX}vw, -10px) scale(${randomScale});
        animation: fall-${i} ${fallDuration}s ${fallDelay}s linear infinite;
      }
      @keyframes fall-${i} {
        ${randomYoyoTime * 100}% {
          transform: translate(${randomXEnd}vw, ${randomYoyoY}vh) scale(${randomScale});
        }
        to {
          transform: translate(${randomXEndYoyo}vw, ${pageHeightVh}vh) scale(${randomScale});
        }
      }
    `;
  }
  addCSS(rule);
}

// Load the rules and execute after the DOM loads
export default function createSnow(snowflakesCount = 200, useColors = false) {
  generateSnowCSS(snowflakesCount, useColors);
  generateSnow(snowflakesCount);
}
