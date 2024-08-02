document.addEventListener('DOMContentLoaded', function () {
    const sections = document.querySelectorAll('.degree-section');
    const navLinks = document.querySelectorAll('.nav-link');
  
    // Añadir evento de clic a cada enlace del menú
    navLinks.forEach(link => {
      link.addEventListener('click', function () {
        navLinks.forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
      });
    });
  
    // Función para detectar la sección activa
    const sectionObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute('id');
          navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').substring(1) === id) {
              link.classList.add('active');
            }
          });
        }
      });
    }, {
      rootMargin: '-100px 0px -50% 0px', // Ajuste el valor según la altura del encabezado fijo
      threshold: [0.1, 0.5, 0.9] // Varias intersecciones para mejor detección
    });
  
    // Observar cada sección
    sections.forEach(section => {
      sectionObserver.observe(section);
    });
  });
  