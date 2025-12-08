<!-- Ausreden-Satzgenerator - Glücksrad-Komponente -->
<!-- Diese Datei wird per Ajax in einen div-Container geladen -->

<div id="wheel-container" class="wheel-fortune-container">
    <!-- Drei Glücksräder nebeneinander -->
    <div class="wheels-wrapper">
        <div class="wheel-section">
            <div class="wheel" id="wheel1">
                <div class="wheel-pointer"></div>
                <div class="wheel-inner">
                    <div class="wheel-segments" id="segments1"></div>
                </div>
            </div>
            <div class="wheel-label">Satzanfang</div>
        </div>

        <div class="wheel-section">
            <div class="wheel" id="wheel2">
                <div class="wheel-pointer"></div>
                <div class="wheel-inner">
                    <div class="wheel-segments" id="segments2"></div>
                </div>
            </div>
            <div class="wheel-label">Satzmitte</div>
        </div>

        <div class="wheel-section">
            <div class="wheel" id="wheel3">
                <div class="wheel-pointer"></div>
                <div class="wheel-inner">
                    <div class="wheel-segments" id="segments3"></div>
                </div>
            </div>
            <div class="wheel-label">Satzende</div>
        </div>
    </div>

    <!-- Ergebnis-Anzeige unter den Rädern -->
    <div class="result-container" id="resultContainer">
        <div class="result-text" id="resultText"></div>
    </div>
</div>

<!-- Stylesheet einbinden -->
<link rel="stylesheet" href="css/wheel.css">

<!-- Audio-Element für Spin-Sound -->
<audio id="spinSound" preload="auto">
    <source src="assets/sounds/spin.mp3" type="audio/mpeg">
</audio>

<!-- JavaScript einbinden -->
<script src="js/wheel.js"></script>
