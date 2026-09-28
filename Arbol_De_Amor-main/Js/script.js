//© Zero - Código libre no comercial


// Cargar el SVG y animar los corazones
fetch('Img/treelove.svg')
  .then(res => res.text())
  .then(svgText => {
    const container = document.getElementById('tree-container');
    container.innerHTML = svgText;
    const svg = container.querySelector('svg');
    if (!svg) return;

    // Animación de "dibujo" para todos los paths
    const allPaths = Array.from(svg.querySelectorAll('path'));
    allPaths.forEach(path => {
      path.style.stroke = '#222';
      path.style.strokeWidth = '2.5';
      path.style.fillOpacity = '0';
      const length = path.getTotalLength();
      path.style.strokeDasharray = length;
      path.style.strokeDashoffset = length;
      path.style.transition = 'none';
    });

    // Forzar reflow y luego animar
    setTimeout(() => {
      allPaths.forEach((path, i) => {
        path.style.transition = `stroke-dashoffset 1.2s cubic-bezier(.77,0,.18,1) ${i * 0.08}s, fill-opacity 0.5s ${0.9 + i * 0.08}s`;
        path.style.strokeDashoffset = 0;
        setTimeout(() => {
          path.style.fillOpacity = '1';
          path.style.stroke = '';
          path.style.strokeWidth = '';
        }, 1200 + i * 80);
      });

      // Después de la animación de dibujo, mueve y agranda el SVG
      const totalDuration = 1200 + (allPaths.length - 1) * 80 + 500;
      setTimeout(() => {
        svg.classList.add('move-and-scale');
        // Mostrar texto con efecto typing
        setTimeout(() => {
          showDedicationText();
          // Mostrar petalos flotando
          startFloatingObjects();
          // Mostrar cuenta regresiva
          showCountdown();
        
        }, 1200); //Tiempo para agrandar el SVG
      }, totalDuration);
    }, 50);

    // Selecciona los corazones (formas rojas)
    const heartPaths = allPaths.filter(el => {
      const style = el.getAttribute('style') || '';
      return style.includes('#FC6F58') || style.includes('#C1321F');
    });
    heartPaths.forEach(path => {
      path.classList.add('animated-heart');
    });
  });

// Efecto máquina de escribir para el texto de dedicatoria (seguidores)
function getURLParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}
// Efecto máquina de escribir para el texto de dedicatoria
function getURLParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

function showDedicationText() {
  let text = getURLParam('text');

  if (!text) {
    text = `Alesia, quiero que sepas que te amo con todo mi ser y que aunque ahora mismo estemos pasando por una situación que quizá nos esté haciendo sentir cosas difíciles, eso no cambia todo lo que siento por ti ni todo lo que quiero construir a tu lado, sé que he cometido errores y quiero pedirte perdón especialmente por aquellas veces en las que he bromeado con cosas que no debía, quizá para mí pudieron parecer simples bromas en algún momento, pero entiendo que hay cosas con las que no se juega cuando se trata de los sentimientos de la persona que amas, y jamás quisiera que una palabra mía, una broma o una actitud te haga pensar que no te tomo en serio o que no sé lo que quiero contigo, porque sí lo sé, te amo a ti, quiero seguir compartiendo mi vida contigo, quiero que podamos superar cada situación que se nos presente y aprender de todo lo que nos ha pasado, quiero que cuando miremos atrás podamos decir que incluso en los momentos difíciles elegimos hablar, entendernos, perdonarnos y seguir intentando, porque para mí lo nuestro vale muchísimo, no quiero que dudes de lo que siento ni de las intenciones que tengo contigo, porque cuando pienso en mi futuro también apareces tú, pienso en nosotros creciendo juntos, cumpliendo nuestros sueños, teniendo nuestro hogar, casándonos algún día, formando una familia, teniendo nuestros hijos y poder mirar atrás sabiendo que todo lo que vivimos nos ayudó a llegar hasta ahí, quiero conocer cada versión de ti, estar en tus días buenos y también acompañarte cuando las cosas no estén bien, quiero celebrar tus logros, apoyarte cuando tengas miedo, escucharte cuando necesites hablar y aprender a quererte cada día de una manera más bonita y más madura, sé que el amor no significa que nunca vamos a equivocarnos ni que todo siempre será perfecto, sé que vamos a tener diferencias, momentos difíciles y cosas que tendremos que aprender a solucionar, pero también sé que las cosas pueden mejorar cuando los dos ponemos de nuestra parte y cuando existe amor de verdad, por eso hoy no quiero que te quedes solamente con mis errores ni con las cosas que hice mal, quiero que también recuerdes todo lo que siento por ti y todo lo que sueño para nosotros, perdóname por las veces en las que no pensé antes de hablar o actuar, perdóname si alguna vez hice que sintieras que no eras suficiente o que no eras importante para mí, porque eres alguien que ocupa un lugar enorme en mi corazón y jamás quisiera perder de vista lo especial que eres para mí, quiero seguir construyendo contigo, quiero que podamos sanar lo que nos haya dolido, mejorar lo que tengamos que mejorar y demostrar con hechos todo aquello que a veces las palabras no pueden explicar, no quiero prometerte una vida perfecta porque sé que eso no existe, pero sí quiero prometerte que mientras sigamos caminando juntos voy a querer aprender, crecer y ser mejor para mí y también para nosotros, quiero que algún día podamos recordar este momento y decir que lo superamos juntos, que no dejamos que una situación difícil acabara con todo lo bonito que todavía podemos vivir, porque yo todavía tengo muchísimos sueños contigo, muchísimas cosas que quiero conocer a tu lado y muchísimos momentos que quiero guardar para siempre, Alesia, te amo muchísimo y quiero que nunca una broma, un error o un momento complicado te haga olvidar eso, quiero que tengas claro que lo que deseo contigo es real, quiero amarte, cuidarte, respetarte, crecer contigo, casarme contigo, formar nuestra familia y ser felices juntos, y aunque ahora quizá no tengamos todas las respuestas, quiero que podamos encontrarlas poco a poco, juntos, porque mi corazón sigue teniendo un lugar para ti y mis deseos para el futuro siguen teniendo tu nombre en ellos`;
  } else {
    text = decodeURIComponent(text).replace(/\\n/g, '\n');
  }

  const container = document.getElementById('dedication-text');

  container.classList.add('typing');

  let i = 0;

  function type() {
    if (i <= text.length) {
      container.textContent = text.slice(0, i);
      i++;

      setTimeout(
        type,
        text[i - 2] === '\n' ? 350 : 45
      );
    } else {
      setTimeout(showSignature, 600);
    }
  }

  type();
}
// Firma manuscrita animada
function showSignature() {
  // Cambia para buscar la firma dentro del contenedor de dedicatoria
  const dedication = document.getElementById('dedication-text');
  let signature = dedication.querySelector('#signature');
  if (!signature) {
    signature = document.createElement('div');
    signature.id = 'signature';
    signature.className = 'signature';
    dedication.appendChild(signature);
  }
  let firma = getURLParam('firma');
  signature.textContent = firma ? decodeURIComponent(firma) : "Con amor, Zero";
  signature.classList.add('visible');
}



// Controlador de objetos flotantes
function startFloatingObjects() {
  const container = document.getElementById('floating-objects');
  let count = 0;
  function spawn() {
    let el = document.createElement('div');
    el.className = 'floating-petal';
    // Posición inicial
    el.style.left = `${Math.random() * 90 + 2}%`;
    el.style.top = `${100 + Math.random() * 10}%`;
    el.style.opacity = 0.7 + Math.random() * 0.3;
    container.appendChild(el);

    // Animación flotante
    const duration = 6000 + Math.random() * 4000;
    const drift = (Math.random() - 0.5) * 60;
    setTimeout(() => {
      el.style.transition = `transform ${duration}ms linear, opacity 1.2s`;
      el.style.transform = `translate(${drift}px, -110vh) scale(${0.8 + Math.random() * 0.6}) rotate(${Math.random() * 360}deg)`;
      el.style.opacity = 0.2;
    }, 30);

    // Eliminar después de animar
    setTimeout(() => {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, duration + 2000);

    // Generar más objetos
    if (count++ < 32) setTimeout(spawn, 350 + Math.random() * 500);
    else setTimeout(spawn, 1200 + Math.random() * 1200);
  }
  spawn();
}

// Cuenta regresiva o fecha especial
// Cuenta los días desde que empezaron y muestra el próximo aniversario
function showCountdown() {
  const container = document.getElementById('countdown');

  // Fecha en la que empezaron: 22 de abril de 2024
  const startDate = new Date(2024, 3, 22, 0, 0, 0);

  function update() {
    const now = new Date();

    // Días que llevan juntos
    const diff = now - startDate;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    // Próximo aniversario
    let nextAnniversary = new Date(
      now.getFullYear(),
      3, // Abril
      22,
      0, 0, 0
    );

    // Si el aniversario de este año ya pasó,
    // usamos el del próximo año
    if (now >= nextAnniversary) {
      nextAnniversary = new Date(
        now.getFullYear() + 1,
        3,
        22,
        0, 0, 0
      );
    }

    // Tiempo restante hasta el próximo aniversario
    const eventDiff = nextAnniversary - now;

    const eventDays = Math.floor(
      eventDiff / (1000 * 60 * 60 * 24)
    );

    const eventHours = Math.floor(
      (eventDiff / (1000 * 60 * 60)) % 24
    );

    const eventMinutes = Math.floor(
      (eventDiff / (1000 * 60)) % 60
    );

    const eventSeconds = Math.floor(
      (eventDiff / 1000) % 60
    );

    container.innerHTML =
      `Llevamos juntos: <b>${days}</b> días<br>` +
      `Nuestro aniversario: <b>${eventDays}d ${eventHours}h ${eventMinutes}m ${eventSeconds}s</b>`;

    container.classList.add('visible');
  }

  update();
  setInterval(update, 1000);
}
// Botón sorpresa
function createSurpriseButton() {
  let btn = document.getElementById('music-btn');

  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'music-btn';
    document.body.appendChild(btn);
  }

  btn.textContent = 'PRESIONAME';

  btn.onclick = () => {
    let photo = document.getElementById('surprise-photo');
    let closeBtn = document.getElementById('close-surprise');

    if (!photo) {
      photo = document.createElement('img');
      photo.id = 'surprise-photo';
      photo.src = 'Music/foto.jpg';
      photo.alt = 'Una sorpresa para ti';
      document.body.appendChild(photo);

      // Botón X
      closeBtn = document.createElement('button');
      closeBtn.id = 'close-surprise';
      closeBtn.textContent = '×';
      closeBtn.setAttribute('aria-label', 'Cerrar imagen');
      document.body.appendChild(closeBtn);

      closeBtn.onclick = () => {
        photo.classList.remove('show');
        closeBtn.classList.remove('show');
      };

      setTimeout(() => {
        photo.classList.add('show');
        closeBtn.classList.add('show');
      }, 50);

    } else {
      photo.classList.add('show');
      closeBtn.classList.add('show');
    }
  };
}

// Crear el botón cuando cargue la página
window.addEventListener('DOMContentLoaded', () => {
  createSurpriseButton();
});

