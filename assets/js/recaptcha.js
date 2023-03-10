var isInvisibleRecaptcha = (el) => {
    return 'size' in el.dataset && el.dataset.size == 'invisible';
}

var isRecaptchaV3 = (el) => {
    return el.classList.contains('google-recaptcha-v3');
}

var recaptchaOnLoad = () => {
    let recaptchas = document.querySelectorAll('.g-recaptcha');
    recaptchas.forEach((recaptcha) => {
        let form = recaptcha.closest('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (isInvisibleRecaptcha(recaptcha) || isRecaptchaV3(recaptcha)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    if (isRecaptchaV3(recaptcha)) {
                        let action = form.dataset.request ?? 'submit';
                        action = action.replace(/[^a-z]/gi, '');
                        grecaptcha.execute(
                            recaptcha.dataset.siteKey,
                            { action: action }
                        ).then((token) => {
                            recaptchaCallback(token);
                        });
                    } else {
                        grecaptcha.execute();
                    }
                    return false;
                }
            });
        }
    });
};

var recaptchaCallback = (token) => {
    let input = document.querySelector('#g-recaptcha-response');
    let recaptcha = input ? input.closest('.g-recaptcha') : null;
    let form = recaptcha ? recaptcha.closest('form') : null;

    if (input) {
        input.value = token;
    }

    if (
        recaptcha &&
        form &&
        (isInvisibleRecaptcha(recaptcha) || isRecaptchaV3(recaptcha))
    ) {
        if ('request' in form.dataset) {
            oc.request(form, form.dataset.request);
        } else {
            form.submit();
        }
    }
}