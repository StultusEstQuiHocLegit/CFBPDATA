// app.js
// Handles UI interactions and AJAX calls for the dashboard.

document.addEventListener('DOMContentLoaded', function () {
    const trainForm = document.getElementById('trainForm');
    const predictForm = document.getElementById('predictForm');
    const logConsole = document.getElementById('logConsole');
    const resultsDiv = document.getElementById('results');
    const modelCard = document.getElementById('modelCard');
    const trainProgressContainer = document.getElementById('trainProgressContainer');
    const trainProgressBar = document.getElementById('trainProgressBar');
    const trainProgressStatus = document.getElementById('trainProgressStatus');
    const trainLogs = document.getElementById('trainLogs');

    function appendLogs(target, logs) {
        if (logs === undefined || logs === null) {
            return;
        }
        const entries = Array.isArray(logs) ? logs : [String(logs)];
        if (entries.length === 0) {
            return;
        }
        const node = target || logConsole;
        if (!node) return;
        node.classList.remove('hidden');
        if (node.hasAttribute('hidden')) {
            node.removeAttribute('hidden');
        }
        node.textContent += entries.join('\n') + '\n';
        node.scrollTop = node.scrollHeight;
    }

    function beginTrainProgress() {
        if (trainProgressContainer) {
            trainProgressContainer.classList.remove('hidden');
            if (trainProgressContainer.hasAttribute('hidden')) {
                trainProgressContainer.removeAttribute('hidden');
            }
        }
        if (trainLogs) {
            trainLogs.textContent = '';
            trainLogs.classList.remove('hidden');
            if (trainLogs.hasAttribute('hidden')) {
                trainLogs.removeAttribute('hidden');
            }
        }
        if (trainProgressBar) {
            trainProgressBar.classList.remove('complete', 'error');
            trainProgressBar.classList.add('indeterminate');
        }
        if (trainProgressStatus) {
            trainProgressStatus.textContent = 'Training in progress…';
        }
    }

    function markTrainComplete(message = 'Training complete.') {
        if (trainProgressBar) {
            trainProgressBar.classList.remove('indeterminate', 'error');
            trainProgressBar.classList.add('complete');
        }
        if (trainProgressStatus) {
            trainProgressStatus.textContent = message;
        }
    }

    function markTrainError(message) {
        if (trainProgressBar) {
            trainProgressBar.classList.remove('indeterminate', 'complete');
            trainProgressBar.classList.add('error');
        }
        if (trainProgressStatus) {
            trainProgressStatus.textContent = message;
        }
    }

    function getTrainEndpoint() {
        if (!trainForm) {
            return window.location.pathname;
        }
        return (
            trainForm.getAttribute('data-endpoint') ||
            trainForm.getAttribute('action') ||
            window.location.pathname
        );
    }

    async function refreshModelCard() {
        if (!modelCard) {
            return;
        }
        const endpoint = getTrainEndpoint();
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({ action: 'model_summary' }).toString()
            });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ' ' + res.statusText);
            }
            const json = await res.json();
            if (!json || json.status !== 'ok' || !json.data || typeof json.data.html !== 'string') {
                throw new Error('Unexpected response payload');
            }
            modelCard.innerHTML = json.data.html;
        } catch (error) {
            const message = 'Failed to refresh model card: ' + (error && error.message ? error.message : String(error));
            appendLogs(trainLogs, message);
            appendLogs(logConsole, message);
        }
    }

    if (trainForm) {
        trainForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (logConsole) {
                logConsole.textContent = '';
            }
            if (resultsDiv) {
                resultsDiv.innerHTML = '';
            }
            beginTrainProgress();
            const formData = new FormData(trainForm);
            let res;
            try {
                res = await fetch(getTrainEndpoint(), {
                    method: 'POST',
                    body: formData
                });
            } catch (err) {
                const message = 'Failed to contact server: ' + err.message;
                markTrainError('Connection issue.');
                appendLogs(trainLogs, message);
                appendLogs(logConsole, message);
                return;
            }
            let json;
            try {
                json = await res.json();
            } catch (err) {
                const message = 'Failed to parse server response: ' + err.message;
                markTrainError('Invalid server response.');
                appendLogs(trainLogs, message);
                appendLogs(logConsole, message);
                return;
            }
            if (!res.ok || !json || json.status !== 'ok') {
                const message = json && json.message ? 'Error: ' + json.message : 'Unexpected response from server';
                markTrainError('Training failed.');
                appendLogs(trainLogs, message);
                appendLogs(logConsole, message);
                return;
            }
            markTrainComplete();
            const data = json.data || {};
            const logs = Object.prototype.hasOwnProperty.call(data, 'logs') ? data.logs : [];
            appendLogs(trainLogs, logs);
            appendLogs(logConsole, logs);
            const metrics = data.metrics || null;
            if (metrics && typeof metrics.pr_auc === 'number' && typeof metrics.roc_auc === 'number' && typeof metrics.brier === 'number' && resultsDiv) {
                const summary = document.createElement('div');
                summary.innerHTML = '<p>PR AUC: ' + metrics.pr_auc.toFixed(3) + ' | ROC AUC: ' + metrics.roc_auc.toFixed(3) + ' | Brier: ' + metrics.brier.toFixed(4) + '</p>';
                resultsDiv.appendChild(summary);
            }
            await refreshModelCard();
        });
    }

    if (predictForm) {
        predictForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            logConsole.textContent = '';
            resultsDiv.innerHTML = '';
            const formData = new FormData(predictForm);
            let res;
            try {
                res = await fetch(predictForm.getAttribute('data-endpoint') || predictForm.getAttribute('action') || window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
            } catch (err) {
                appendLogs(logConsole, 'Failed to contact server: ' + err.message);
                return;
            }
            let json;
            try {
                json = await res.json();
            } catch (err) {
                appendLogs(logConsole, 'Failed to parse server response: ' + err.message);
                return;
            }
            if (!res.ok || !json || json.status !== 'ok') {
                appendLogs(logConsole, json && json.message ? 'Error: ' + json.message : 'Unexpected response from server');
                return;
            }
            const data = json.data || {};
            const logs = Object.prototype.hasOwnProperty.call(data, 'logs') ? data.logs : [];
            appendLogs(logConsole, logs);
            const rows = Array.isArray(data.results) ? data.results : [];
            const table = document.createElement('table');
            const thead = document.createElement('thead');
            thead.innerHTML = '<tr><th>Company</th><th>Year</th><th>P(Default)</th><th>Risk</th><th>Top Features</th></tr>';
            table.appendChild(thead);
            const tbody = document.createElement('tbody');
            rows.forEach(function (r) {
                const tf = Object.entries(r.top_features || {}).map(function (entry) {
                    return entry[0] + ':' + entry[1].toFixed(3);
                }).join(', ');
                const tr = document.createElement('tr');
                tr.innerHTML = '<td>' + r.company_id + '</td><td>' + r.fiscal_year + '</td><td>' + r.p_default_12m.toFixed(3) + '</td><td>' + r.risk_bucket + '</td><td>' + tf + '</td>';
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            resultsDiv.appendChild(table);
        });
    }
});
