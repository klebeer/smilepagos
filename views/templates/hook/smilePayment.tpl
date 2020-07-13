<section>

    <style>
        .wpwl-wrapper-submit {
            display: none;
        }
    </style>

    <script async src="{$checkScript}"></script>

    <script>

        const waitUntilElementExistsSelector = (selector, callback) => {
            const element = document.querySelector(selector);
            if (element) {
                return callback(element);
            }
            setTimeout(() => waitUntilElementExistsSelector(selector, callback), 500);
        }

        const waitUntilElementExistsId = (elementId, callback) => {
            const element = document.getElementById(elementId);
            if (element) {
                return callback(element);
            }
            setTimeout(() => waitUntilElementExistsId(elementId, callback), 500);
        }

        waitUntilElementExistsSelector("input[data-module-name='smilepagos']", (smilePayment) => {
            smilePayment.addEventListener('change', function () {
                const paymentButton = document.querySelector("div.ps-shown-by-js > button:nth-child(1)");
                if (smilePayment && smilePayment.checked) {
                    paymentButton.style.display = "none";
                }
            });
        });


        waitUntilElementExistsSelector("input[data-module-name='ps_wirepayment']", (wirepayment) => {
            wirepayment.addEventListener('change', function () {
                const paymentButton = document.querySelector("div.ps-shown-by-js > button:nth-child(1)");
                if (wirepayment && wirepayment.checked) {
                    paymentButton.style.display = "block";
                }
            });
        });


        waitUntilElementExistsId("conditions_to_approve[terms-and-conditions]", (conditions) => {

            conditions.addEventListener('change', function () {
                if (conditions.checked) {
                    document.querySelector('.wpwl-wrapper-submit').style.display = "block";
                } else {
                    document.querySelector('.wpwl-wrapper-submit').style.display = "none";
                }
            });

        });

        var wpwlOptions = {
            style: "card",
            locale: "es",
            maskCvv: true,
            brandDetection: true,
            showCVVHint: true,
            onBeforeSubmitCard: function (e) {
                const holder = $('.wpwl-control-cardHolder').val();
                if (holder.trim().length < 2) {
                    $('.wpwl-control-cardHolder').addClass('wpwl-has-error').after('<div class="wpwl-hint wpwl-hint-cardHolderError">Nombre del titular de la tarjeta no válido</div>');
                    return false;
                }
                return true;
            },
            labels: {
                cvv: "CVV"
            }
        }
    </script>
    <form action="{$action}" class="paymentWidgets" id="smilePaymentForm"
          data-brands="VISA MASTER AMEX DINERS DISCOVER">
    </form>


</section>
