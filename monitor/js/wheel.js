/**
 * Ausreden-Satzgenerator - JavaScript Controller
 * Steuert die Glücksrad-Animationen und Satzgenerierung
 */

(function() {
    'use strict';

    // Globale Variablen
    var wheelData = null;
    var isSpinning = false;
    var spinSound = null;
    var SPIN_DURATION = 3000; // Millisekunden (wird aus Audio-Länge angepasst)

    /**
     * Initialisierung beim Laden
     */
    function init() {
        spinSound = document.getElementById('spinSound');
        
        // Audio-Dauer ermitteln
        if (spinSound) {
            spinSound.addEventListener('loadedmetadata', function() {
                SPIN_DURATION = spinSound.duration * 1000;
            });
        }

        // JSON-Daten laden
        loadWheelData();
    }

    /**
     * Lädt die Glücksrad-Daten aus JSON
     */
    function loadWheelData() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'assets/data/WheelOfFortune.json', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                wheelData = JSON.parse(xhr.responseText);
                buildWheelSegments();
            } else {
                console.error('Fehler beim Laden der Wheel-Daten');
            }
        };
        xhr.send();
    }

    /**
     * Erstellt die visuellen Segmente der Glücksräder
     * Nach dev.to Artikel von Mads Stoumann
     */
    function buildWheelSegments() {
        if (!wheelData) return;

        for (var i = 1; i <= 3; i++) {
            var segments = wheelData['wheel' + i];
            var container = document.getElementById('segments' + i);
            var wheelContainer = document.getElementById('wheel' + i);
            
            if (!container || !segments || !wheelContainer) continue;

            var numSegments = segments.length;
            
            // CSS Variable für Anzahl der Items setzen
            wheelContainer.style.setProperty('--wheel-items', numSegments);
            
            var segmentAngle = 360 / numSegments;
            
            for (var j = 0; j < numSegments; j++) {
                // Segment erstellen (direkt als div, kein li)
                var segment = document.createElement('div');
                segment.className = 'wheel-segment';
                
                // Index-Variable für Rotation setzen
                segment.style.setProperty('--segment-idx', j);
                
                // Rotation berechnen
                var rotation = segmentAngle * j;
                segment.style.rotate = rotation + 'deg';
                
                // Text direkt im Segment (kein span nötig)
                segment.textContent = segments[j];
                
                container.appendChild(segment);
            }
        }
    }

    /**
     * Hauptfunktion zum Starten des Spins
     * @param {number} index1 - Ziel-Index für Rad 1 (0-basiert)
     * @param {number} index2 - Ziel-Index für Rad 2 (0-basiert)
     * @param {number} index3 - Ziel-Index für Rad 3 (0-basiert)
     */
    window.startSpin = function(index1, index2, index3) {
        if (isSpinning || !wheelData) return;
        
        isSpinning = true;

        // Ergebnis-Container leeren
        var resultText = document.getElementById('resultText');
        if (resultText) {
            resultText.innerHTML = '';
            resultText.style.opacity = '0';
        }

        // Räder nacheinander drehen
        spinWheel(1, index1, function() {
            spinWheel(2, index2, function() {
                spinWheel(3, index3, function() {
                    // Alle Räder fertig
                    isSpinning = false;
                });
            });
        });
    };

    /**
     * Dreht ein einzelnes Rad
     * @param {number} wheelNum - Nummer des Rads (1-3)
     * @param {number} targetIndex - Ziel-Index im Array
     * @param {function} callback - Callback nach Beendigung
     */
    function spinWheel(wheelNum, targetIndex, callback) {
        var wheelInner = document.querySelector('#wheel' + wheelNum + ' .wheel-inner');
        if (!wheelInner) {
            console.error('wheel-inner nicht gefunden für Rad ' + wheelNum);
            return;
        }

        var segments = wheelData['wheel' + wheelNum];
        if (!segments || targetIndex < 0 || targetIndex >= segments.length) {
            console.error('Ungültiger Index für Rad ' + wheelNum);
            return;
        }

        // Sound abspielen und Dauer ermitteln
        var actualDuration = SPIN_DURATION;
        if (spinSound) {
            spinSound.currentTime = 0;
			spinSound.volume = 0.05;
            spinSound.play();
            // Verwende tatsächliche Sound-Dauer falls verfügbar
            if (spinSound.duration && spinSound.duration > 0) {
                actualDuration = spinSound.duration * 1000;
            }
        }

        // Berechne Ziel-Rotation
        var segmentAngle = 360 / segments.length;
        var extraSpins = 20; // Mehr Umdrehungen für dramatischen Effekt
        // Anpassung: +90 Grad weil Segmente um 90 Grad rotiert sind
        var targetAngle = (extraSpins * 360) + (360 - (targetIndex * segmentAngle)) + 90;
        
        console.log('Rad ' + wheelNum + ' dreht zu Index ' + targetIndex + ' mit ' + targetAngle + ' Grad');
        
        // Transition-Duration dynamisch setzen
        wheelInner.style.transitionDuration = (actualDuration / 1000) + 's';
        
        // Animation hinzufügen
        wheelInner.classList.add('spinning');
        wheelInner.style.transform = 'rotate(' + targetAngle + 'deg)';

        // Nach Spin-Dauer: Animation beenden
        setTimeout(function() {
            wheelInner.classList.remove('spinning');
            console.log('Rad ' + wheelNum + ' fertig');
            
            // Zeige Teilergebnis nach diesem Rad
            showPartialResult(wheelNum, targetIndex);
            
            // Kurze Pause vor nächstem Rad
            setTimeout(function() {
                if (callback) callback();
            }, 300);
        }, actualDuration);
    }

    /**
     * Zeigt Teilergebnis nach einem gedrehten Rad
     * @param {number} wheelNum - Nummer des fertig gedrehten Rads
     * @param {number} index - Gewählter Index
     */
    function showPartialResult(wheelNum, index) {
        var resultText = document.getElementById('resultText');
        if (!resultText) return;
        
        var part = wheelData['wheel' + wheelNum][index];
        var span = document.createElement('span');
        span.className = 'result-part result-part-' + wheelNum;
        span.textContent = part + ' ';
        
        resultText.appendChild(span);
        resultText.style.opacity = '1';
    }

    /**
     * Zeigt das Endergebnis an
     * @param {number} index1 - Gewählter Index Rad 1
     * @param {number} index2 - Gewählter Index Rad 2
     * @param {number} index3 - Gewählter Index Rad 3
     */
    function showResult(index1, index2, index3) {
        var part1 = wheelData.wheel1[index1];
        var part2 = wheelData.wheel2[index2];
        var part3 = wheelData.wheel3[index3];

        var fullSentence = part1 + ' ' + part2 + ' ' + part3;

        var resultText = document.getElementById('resultText');
        if (resultText) {
            // Kurze Verzögerung vor Anzeige
            setTimeout(function() {
                resultText.textContent = fullSentence;
                resultText.style.opacity = '1';
            }, 500);
        }
    }

    // Auto-Initialisierung
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
