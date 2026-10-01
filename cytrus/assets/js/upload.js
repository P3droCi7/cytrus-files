// Large uploads on shared hosting hit a hard ~60s proxy timeout. Splitting into small
// chunks keeps every single HTTP request short, regardless of total file size or link speed.
(function () {
    var CHUNK_SIZE = 4 * 1024 * 1024; // 4MB - safe margin even on slow connections

    function uuid() {
        if (window.crypto && crypto.getRandomValues) {
            var bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            return Array.prototype.map.call(bytes, function (b) {
                return b.toString(16).padStart(2, '0');
            }).join('');
        }
        return Date.now().toString(16) + Math.random().toString(16).slice(2);
    }

    function sendChunk(formData, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php?p=upload_chunk', true);
            xhr.timeout = 0;
            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    onProgress(e.loaded);
                }
            });
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (err) {
                        reject(new Error('Nieprawidłowa odpowiedź serwera.'));
                    }
                } else {
                    reject(new Error('HTTP ' + xhr.status));
                }
            });
            xhr.addEventListener('error', function () { reject(new Error('Błąd sieci.')); });
            xhr.addEventListener('timeout', function () { reject(new Error('Przekroczono czas oczekiwania.')); });
            xhr.send(formData);
        });
    }

    async function uploadFile(file, dir, csrfToken, onProgress, onFinalizing) {
        var uploadId = uuid();
        var totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
        var sentBytes = 0;

        for (var i = 0; i < totalChunks; i++) {
            var start = i * CHUNK_SIZE;
            var end = Math.min(file.size, start + CHUNK_SIZE);
            var formData = new FormData();
            formData.append('_csrf', csrfToken);
            formData.append('dir', dir);
            formData.append('upload_id', uploadId);
            formData.append('filename', file.name);
            formData.append('chunk_index', String(i));
            formData.append('total_chunks', String(totalChunks));
            formData.append('chunk', file.slice(start, end), file.name);

            var response = null;
            var lastError = null;
            for (var attempt = 0; attempt < 3 && !response; attempt++) {
                try {
                    response = await sendChunk(formData, function (loaded) {
                        onProgress(sentBytes + loaded);
                    });
                } catch (err) {
                    lastError = err;
                }
            }
            if (!response) {
                throw lastError || new Error('Nie udało się przesłać fragmentu pliku.');
            }
            if (!response.ok) {
                throw new Error(response.error || 'Serwer odrzucił fragment pliku.');
            }
            sentBytes = end;
            onProgress(sentBytes);

            if (response.processing) {
                onFinalizing();
                await waitForAssembly(uploadId);
            }
        }
    }

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    // Final chunk returns immediately (processing:true) while the server keeps assembling the
    // file in the background, so poll until it reports done instead of blindly waiting.
    async function waitForAssembly(uploadId) {
        var maxAttempts = 600; // up to 20 minutes for very large files on slow disks
        for (var attempt = 0; attempt < maxAttempts; attempt++) {
            await sleep(2000);
            var res = await fetch('index.php?p=upload_status&upload_id=' + encodeURIComponent(uploadId), {
                credentials: 'same-origin',
            });
            var status = await res.json();
            if (status.done) {
                if (!status.ok) {
                    throw new Error(status.error || 'Nie udało się dokończyć zapisu pliku na serwerze.');
                }
                return;
            }
        }
        throw new Error('Serwer zbyt długo składa plik. Sprawdź listę plików ręcznie za chwilę.');
    }

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

            var files = Array.prototype.slice.call(fileInput.files);
            var dir = form.querySelector('input[name="dir"]').value;
            var csrfToken = form.querySelector('input[name="_csrf"]').value;
            var totalSize = files.reduce(function (sum, f) { return sum + f.size; }, 0) || 1;
            var completedSize = 0;

            progressWrap.hidden = false;
            progressBar.style.width = '0%';
            progressLabel.textContent = 'Przesyłanie 0%';
            if (submitButton) {
                submitButton.disabled = true;
            }

            function updateProgress(currentFileBytes) {
                var percent = Math.min(100, Math.round(((completedSize + currentFileBytes) / totalSize) * 100));
                progressBar.style.width = percent + '%';
                progressLabel.textContent = 'Przesyłanie ' + percent + '%';
            }

            (async function () {
                try {
                    for (var i = 0; i < files.length; i++) {
                        await uploadFile(files[i], dir, csrfToken, updateProgress, function () {
                            progressLabel.textContent = 'Serwer zapisuje plik na dysku...';
                        });
                        completedSize += files[i].size;
                    }
                    progressLabel.textContent = 'Gotowe, odświeżanie listy...';
                    window.location.href = 'index.php?p=files&dir=' + encodeURIComponent(dir);
                } catch (err) {
                    progressLabel.textContent = 'Błąd: ' + err.message;
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            })();
        });
    });
})();

