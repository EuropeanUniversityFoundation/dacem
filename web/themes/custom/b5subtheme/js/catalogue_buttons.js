
document.addEventListener('DOMContentLoaded', function() {
const titulacionesButton = document.getElementById('titulaciones-button');
const asignaturasButton = document.getElementById('asignaturas-button');
const searchButton = document.getElementById('search-button');
const searchInput = document.getElementById('search-input');

titulacionesButton.addEventListener('click', function() {
    titulacionesButton.classList.add('selected');
    asignaturasButton.classList.remove('selected');
});

asignaturasButton.addEventListener('click', function() {
    asignaturasButton.classList.add('selected');
    titulacionesButton.classList.remove('selected');
});

searchButton.addEventListener('click', function() {
    const searchTerm = searchInput.value.toLowerCase();
    const carreras = document.querySelectorAll('.program-list li');

    carreras.forEach(function(carrera) {
    const title = carrera.textContent.toLowerCase();
    if (title.includes(searchTerm)) {
        carrera.style.display = 'list-item';
    } else {
        carrera.style.display = 'none';
    }
    });
});
});

