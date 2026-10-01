// Classic <form> submits give no upload progress, so intercept with XHR and follow the server's redirect manually.
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('uploadForm');
    if (!form) {
        return;
    }

    var progressWrap = document.getElementById('uploadProgress');
    var progressBar = document.getElementById('uploadProgressBar');
    var progressLabel = document.getElementById('uploadProgressLabel');
    var submitButton = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function (event) {
        var fileInput = form.querySelector('input[type="file"]');
        if (!fileInput || fileInput.files.length === 0) {
            return; // let the browser show its own "required" validation
        }
        event.preventDefault();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.timeout = 0; // let large uploads run as long as needed, don't let the browser time out on its own

        progressWrap.hidden = false;
        progressBar.style.width = '0%';
        progressLabel.textContent = '0%';
        if (submitButton) {
            submitButton.disabled = true;
        }

        xhr.upload.addEventListener('progress', function (e) {
            if (!e.lengthComputable) {
                return;
            }
            var percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressLabel.textContent = percent + '%';
        });

        xhr.addEventListener('load', function () {
            if (xhr.status >= 200 && xhr.status < 400) {
                // Server replies with a redirect to the files view; follow it to show flash messages.
                window.location.href = xhr.responseURL || form.action;
                return;
            }
            progressLabel.textContent = 'Serwer odrzucił plik (HTTP ' + xhr.status + '). Sprawdź limity PHP/serwera.';
            if (submitButton) {
                submitButton.disabled = false;
            }
        });

        xhr.addEventListener('error', function () {
            progressLabel.textContent = 'Błąd sieci podczas przesyłania. Spróbuj ponownie.';
            if (submitButton) {
                submitButton.disabled = false;
            }
        });

        xhr.addEventListener('abort', function () {
            progressLabel.textContent = 'Przesyłanie przerwane.';
            if (submitButton) {
                submitButton.disabled = false;
            }
        });

        xhr.addEventListener('timeout', function () {
            progressLabel.textContent = 'Przekroczono czas oczekiwania serwera.';
            if (submitButton) {
                submitButton.disabled = false;
            }
        });

        xhr.send(new FormData(form));
    });
});
