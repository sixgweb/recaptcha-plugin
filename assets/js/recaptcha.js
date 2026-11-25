var isInvisibleRecaptcha = (el) => {
    return 'size' in el.dataset && el.dataset.size == 'invisible';
}

var isRecaptchaV3 = (el) => {
    return el.classList.contains('google-recaptcha-v3');
}

/**
 * v3 action name. Must match Recaptcha::getExpectedAction() on the server:
 * the AJAX handler with only letters kept, or "submit".
 */
var getRecaptchaAction = (form) => {
    let action = (form.dataset.request ?? 'submit').replace(/[^a-z]/gi, '');
    return action || 'submit';
}

/**
 * Intercept the form's submit once, to run invisible v2 / v3 before submitting.
 */
var bindRecaptchaForm = (recaptcha) => {
    let form = recaptcha.closest('form');
    if (!form || form.dataset.recaptchaBound === 'true') {
        return;
    }
    form.dataset.recaptchaBound = 'true';

    form.addEventListener('submit', (e) => {
        //Look up again, the widget may have been replaced by an AJAX partial update
        let current = form.querySelector('.g-recaptcha');
        if (!current || !(isInvisibleRecaptcha(current) || isRecaptchaV3(current))) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        e.stopPropagation();

        if (isRecaptchaV3(current)) {
            grecaptcha.ready(() => {
                grecaptcha.execute(
                    current.dataset.siteKey,
                    { action: getRecaptchaAction(form) }
                ).then((token) => {
                    recaptchaCallback(token, form);
                });
            });
        } else if (current.dataset.widgetId !== undefined) {
            grecaptcha.execute(current.dataset.widgetId);
        } else {
            grecaptcha.execute();
        }
        return false;
    });
}

var passedCallback = (token) => {
    oc.ajax('onCheckRecaptcha', {
        data: {
            'g-recaptcha-response': token
        },
        success: function (data) {
            if (typeof data.recaptchaId != 'undefined') {
                document.getElementById(data.recaptchaId).remove();
            }
            this.success(data);
        },
    });
};

/**
 * Put the token into the given form (or the first reCaptcha form) and submit it.
 */
var recaptchaCallback = (token, form = null) => {
    let input = (form ?? document).querySelector('[name="g-recaptcha-response"]');
    let recaptcha = input ? input.closest('.g-recaptcha') : null;
    form = form ?? (recaptcha ? recaptcha.closest('form') : null);

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

/**
 * Bind forms and render any v2 widgets added since the last run. Safe to call repeatedly.
 */
var initRecaptchas = () => {
    if (typeof grecaptcha === 'undefined') {
        return;
    }

    document.querySelectorAll('.g-recaptcha').forEach((recaptcha) => {
        bindRecaptchaForm(recaptcha);

        if (recaptcha.dataset.recaptchaLoaded === 'true') {
            return;
        }
        recaptcha.dataset.recaptchaLoaded = 'true';

        //Google auto-renders v2 widgets present at page load; render ones added later by AJAX
        if (!isRecaptchaV3(recaptcha) && recaptcha.childElementCount === 0) {
            recaptcha.dataset.widgetId = grecaptcha.render(recaptcha, {
                'sitekey': recaptcha.dataset.sitekey,
                'size': recaptcha.dataset.size,
                'theme': recaptcha.dataset.theme,
                'callback': recaptcha.dataset.callback
            });
        }
    });
}

var recaptchaOnLoad = () => {
    initRecaptchas();
};

(() => {
    addEventListener('page:render', initRecaptchas);
    addEventListener('render', initRecaptchas);
})();
