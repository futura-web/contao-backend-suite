/**
 * Das Chosen-Widget des Felds "Style-Klassen" (backendSuiteStyleSelector,
 * siehe StyleSelectorFieldInjector) behaelt nach einer Auswahl im
 * Mehrfachauswahl-Modus seinen aktiven Fokus-Rahmen, bis der Redakteur
 * irgendwo anders hinklickt - das sieht aus wie ein haengender Fokus-Zustand.
 *
 * Blurt stattdessen das interne Chosen-Suchfeld sofort nach jeder Auswahl,
 * was Chosens eigene Blur-Behandlung ausloest und damit den Fokus-Rahmen
 * entfernt. Ein weiterer Klick auf das Widget oeffnet es danach ganz normal
 * wieder ueber Chosens eigenen Click-Handler - hier wird nichts blockiert,
 * nur der Fokus-Zustand direkt nach der Auswahl zurueckgesetzt.
 *
 * @author Dinko Skopljak <ds@futura-web.de>
 */
(function () {
	'use strict';

	var FIELD_ID = 'ctrl_backendSuiteStyleSelector';

	function blurChosenSearch(select) {
		var container = select.nextElementSibling;

		if (!container || !container.classList.contains('chosen-container')) {
			return;
		}

		var search = container.querySelector('input[type="text"]');

		if (search) {
			search.blur();
		}
	}

	document.addEventListener('change', function (event) {
		var select = event.target;

		if (!select || select.id !== FIELD_ID) {
			return;
		}

		// Chosen setzt seinen "active"-Zustand erst nach dem eigenen
		// change-Handler - deshalb erst im naechsten Tick blurren.
		window.setTimeout(function () {
			blurChosenSearch(select);
		}, 0);
	});
})();
