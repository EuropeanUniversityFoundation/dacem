(function (Drupal, once) {
  Drupal.behaviors.searchProgrammeSort = {
    attach: function (context) {
      once('search-programme-sort', '.catalogue-view', context).forEach(function (wrapper) {
        const form =
          wrapper.querySelector('.catg__filters form') ||
          wrapper.querySelector('form.views-exposed-form') ||
          wrapper.querySelector('form');
        if (!form) return;

        // Desactiva refocus/scroll en el formulario expuesto:
        form.setAttribute('data-disable-refocus', 'true');
        form.querySelectorAll('input, select, textarea, button').forEach(el => {
          el.setAttribute('data-disable-refocus', 'true');
        });

        const sortBy   = form.querySelector('[name="sort_by"]');
        const sortOrder = form.querySelector('[name="sort_order"]');
        if (!sortBy || !sortOrder) return;

        // Lista blanca de opciones válidas según el <select> real:
        const allowed = Array.from(sortBy.options).map(o => o.value);

        // Botones
        wrapper.querySelectorAll('.js-sort').forEach(function (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();

            const by    = btn.getAttribute('data-sort-by');
            const order = btn.getAttribute('data-sort-order') || 'ASC';

            if (!allowed.includes(by)) {
              console.warn('[SORT] valor no permitido:', by, 'permitidos:', allowed);
              return;
            }

            // Marca también los selects con el atributo (por si se recrean):
            sortBy.setAttribute('data-disable-refocus', 'true');
            sortOrder.setAttribute('data-disable-refocus', 'true');

            // Setea valores
            sortBy.value = by;
            sortOrder.value = order;

            // Dispara autosubmit por "change" (sin clicks → no hay scroll/refocus)
            sortBy.dispatchEvent(new Event('change', { bubbles: true }));
            sortOrder.dispatchEvent(new Event('change', { bubbles: true }));
          });
        });

        
        form.addEventListener('change', function (ev) {
          const t = ev.target;
          if (t && t.setAttribute) {
            t.setAttribute('data-disable-refocus', 'true');
          }
        }, true);
      });
    }
  };
})(Drupal, once);



(function (Drupal) {
  const p = Drupal.AjaxCommands && Drupal.AjaxCommands.prototype;
  if (p && p.viewsScrollTop) {
    const orig = p.viewsScrollTop;
    p.viewsScrollTop = function (ajax, response, status) {
      try { return orig.call(this, ajax, response, status); }
      catch (e) { console.warn('Views scroll skipped (no wrapper found)'); }
    };
  }
})(Drupal);


(function (Drupal) {
  // Evita error "offset is undefined" en AJAX de Views.
  const proto = Drupal.AjaxCommands && Drupal.AjaxCommands.prototype;
  if (proto && proto.viewsScrollTop) {
    const original = proto.viewsScrollTop;
    proto.viewsScrollTop = function (ajax, response, status) {
      try {
        // Si el wrapper existe, ejecuta comportamiento normal.
        return original.call(this, ajax, response, status);
      } catch (e) {
        // Si falla, no hace scroll (pero no lanza error).
        console.warn('Views scroll skipped: wrapper not found (safe bypass)');
      }
    };
  }
})(Drupal);




(function ($, Drupal, once) {

  Drupal.behaviors.programmeLiveSearch = {
    attach: function (context) {

      // Wrapper de la vista
      const $wrapper = $(context).find('[data-view-dom-id]');
      if (!$wrapper.length) {
        return;
      }

      // Formulario expuesto de la vista
      const $viewForm = $wrapper.find('form[id^="views-exposed-form"]');
      if (!$viewForm.length) {
        return;
      }

      // Tu input manual
      const $input = $('#programme-search');
      if (!$input.length) {
        return;
      }

      let timer;
      const delay = 300;

      function ensureCombine() {
        let $combine = $viewForm.find('input[name="combine"]');
        if (!$combine.length) {
          $combine = $('<input>', {
            type: 'hidden',
            name: 'combine'
          }).appendTo($viewForm);
        }
        return $combine;
      }

      function resetPage() {
        let $page = $viewForm.find('input[name="page"]');
        if (!$page.length) {
          $page = $('<input>', {
            type: 'hidden',
            name: 'page'
          }).appendTo($viewForm);
        }
        $page.val(0);
      }

      function searchNow() {
        const value = $input.val().trim();
        const $combine = ensureCombine();
        $combine.val(value);

        resetPage();

        // Disparar el botón Apply (AJAX de Views)
        const $submit = $viewForm.find('input[type="submit"], button[type="submit"]').first();
        if ($submit.length) {
          $submit.trigger('click');
        } else {
          $viewForm.trigger('submit');
        }
      }

      // Sincronizar el valor del combine al input tras cada recarga AJAX
      const $combineExisting = $viewForm.find('input[name="combine"]');
      if ($combineExisting.length && !$input.val()) {
        $input.val($combineExisting.val());
      }

      // Volver a enfocar el input y poner el cursor al final
      if ($input.length) {
        const val = $input.val();
        const el = $input[0];
        el.focus();
        if (typeof el.setSelectionRange === 'function') {
          el.setSelectionRange(val.length, val.length);
        }
      }

      // Listener de escritura (con once para no duplicar)
      $(once('programmeLiveSearch', $input)).on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(searchNow, delay);
      });
    }
  };

})(jQuery, Drupal, once);
