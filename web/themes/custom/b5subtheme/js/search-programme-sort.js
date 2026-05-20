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

        form.querySelectorAll('[data-clear-home-programme], [data-clear-custom-filters]').forEach(function (resetButton) {
          resetButton.addEventListener('click', function (e) {
            const resetUrl = resetButton.getAttribute('data-reset-url');
            form.querySelectorAll('[name="home_programme"], [data-home-programme-filter]').forEach(function (field) {
              field.remove();
            });

            if (resetUrl) {
              e.preventDefault();
              e.stopImmediatePropagation();
              window.location.href = resetUrl;
            }
          }, true);
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
  const focusState = {};

  Drupal.behaviors.programmeLiveSearch = {
    attach: function (context) {
      once('programmeLiveSearch', '[data-view-dom-id]', context).forEach(function (wrapper) {
        const $wrapper = $(wrapper);
        const viewDomId = wrapper.getAttribute('data-view-dom-id') || 'default';
        const $viewForm = $wrapper.find('form[id^="views-exposed-form"]').first();
        if (!$viewForm.length) {
          return;
        }

        const $input = $wrapper.find('#programme-search').first();
        if (!$input.length) {
          return;
        }

        let timer = null;
        let isSubmitting = false;
        let isComposing = false;
        let pendingValue = null;
        let lastSubmittedValue = null;
        const delay = 500;

        function captureFocusState() {
          const input = $input[0];
          focusState[viewDomId] = {
            shouldRestore: document.activeElement === input,
            selectionStart: typeof input.selectionStart === 'number' ? input.selectionStart : null,
            selectionEnd: typeof input.selectionEnd === 'number' ? input.selectionEnd : null
          };
        }

        function restoreFocusState(attempt) {
          const state = focusState[viewDomId];
          if (!state || !state.shouldRestore) {
            return;
          }

          const currentAttempt = typeof attempt === 'number' ? attempt : 0;

          const currentWrapper = document.querySelector('[data-view-dom-id="' + viewDomId + '"]');
          if (!currentWrapper) {
            if (currentAttempt < 10) {
              window.setTimeout(function () {
                restoreFocusState(currentAttempt + 1);
              }, 50);
            }
            return;
          }

          const input = currentWrapper.querySelector('#programme-search');
          if (!input) {
            if (currentAttempt < 10) {
              window.setTimeout(function () {
                restoreFocusState(currentAttempt + 1);
              }, 50);
            }
            return;
          }

          input.focus({ preventScroll: true });

          if (typeof input.setSelectionRange === 'function') {
            const valueLength = input.value.length;
            const start = state.selectionStart === null ? valueLength : Math.min(state.selectionStart, valueLength);
            const end = state.selectionEnd === null ? valueLength : Math.min(state.selectionEnd, valueLength);
            input.setSelectionRange(start, end);
          }

          state.shouldRestore = false;
        }

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

        function finishSubmit() {
          isSubmitting = false;
          window.setTimeout(function () {
            restoreFocusState(0);
          }, 0);

          if (pendingValue !== null && pendingValue !== lastSubmittedValue) {
            const valueToSubmit = pendingValue;
            pendingValue = null;
            queueSearch(valueToSubmit);
          }
        }

        function submitSearch(value) {
          if (isSubmitting) {
            pendingValue = value;
            return;
          }

          isSubmitting = true;
          lastSubmittedValue = value;

          const $combine = ensureCombine();
          $combine.val(value);
          resetPage();
          captureFocusState();

          $(document).one('ajaxComplete.programmeLiveSearch', finishSubmit);

          const $submit = $viewForm.find('input[type="submit"], button[type="submit"]').first();
          if ($submit.length) {
            $submit.trigger('click');
            return;
          }

          $viewForm.trigger('submit');
          finishSubmit();
        }

        function queueSearch(forcedValue) {
          clearTimeout(timer);
          timer = setTimeout(function () {
            if (isComposing) {
              return;
            }

            const value = typeof forcedValue === 'string'
              ? forcedValue
              : $input.val().trim();

            if (value === lastSubmittedValue && !isSubmitting) {
              return;
            }

            submitSearch(value);
          }, delay);
        }

        const $combineExisting = $viewForm.find('input[name="combine"]').first();
        if ($combineExisting.length && !$input.val()) {
          $input.val($combineExisting.val());
        }

        restoreFocusState();

        $input.on('compositionstart', function () {
          isComposing = true;
        });

        $input.on('compositionend', function () {
          isComposing = false;
          queueSearch();
        });

        $input.on('input', function () {
          if (isComposing) {
            return;
          }

          queueSearch();
        });
      });
    }
  };

})(jQuery, Drupal, once);
