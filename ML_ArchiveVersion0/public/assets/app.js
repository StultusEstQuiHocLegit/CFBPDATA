// app.js
// Handles UI interactions and AJAX calls for the dashboard.

document.addEventListener('DOMContentLoaded', () => {
    const trainForm = document.getElementById('trainForm');
    const predictForm = document.getElementById('predictForm');
    const logConsole = document.getElementById('logConsole');
    const resultsDiv = document.getElementById('results');
    const trainProgressContainer = document.getElementById('trainProgressContainer');
    const trainProgressBar = document.getElementById('trainProgressBar');
    const trainProgressStatus = document.getElementById('trainProgressStatus');
    const trainLogs = document.getElementById('trainLogs');

    function appendLogs(target, logs) {
        if (!logs) return;
        const entries = Array.isArray(logs) ? logs : [String(logs)];
        if (entries.length === 0) return;
        const node = target ?? logConsole;
        if (!node) return;
        node.classList.remove('hidden');
        node.textContent += entries.join('\n') + '\n';
        node.scrollTop = node.scrollHeight;
    }

    function beginTrainProgress() {
        if (trainProgressContainer) {
            trainProgressContainer.classList.remove('hidden');
        }
        if (trainLogs) {
            trainLogs.textContent = '';
            trainLogs.classList.remove('hidden');
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

    trainForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (logConsole) {
            logConsole.textContent = '';
        }
        resultsDiv.innerHTML = '';
        beginTrainProgress();
        const formData = new FormData(trainForm);
        let res;
        try {
            res = await fetch(window.location.href, {
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
        appendLogs(trainLogs, json.data?.logs ?? []);
        appendLogs(logConsole, json.data?.logs ?? []);
        const m = json.data.metrics;
        const summary = document.createElement('div');
        summary.innerHTML = `<p>PR AUC: ${m.pr_auc.toFixed(3)} | ROC AUC: ${m.roc_auc.toFixed(3)} | Brier: ${m.brier.toFixed(4)}</p>`;
        resultsDiv.appendChild(summary);
    });

    predictForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        logConsole.textContent = '';
        resultsDiv.innerHTML = '';
        const formData = new FormData(predictForm);
        let res;
        try {
            res = await fetch(window.location.href, {
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
        appendLogs(logConsole, json.data?.logs ?? []);
        const rows = json.data.results || [];
        const table = document.createElement('table');
        const thead = document.createElement('thead');
        thead.innerHTML = '<tr><th>Company</th><th>Year</th><th>P(Default)</th><th>Risk</th><th>Top Features</th></tr>';
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        rows.forEach(r => {
            const tf = Object.entries(r.top_features || {}).map(([k,v]) => `${k}:${v.toFixed(3)}`).join(', ');
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.company_id}</td><td>${r.fiscal_year}</td><td>${r.p_default_12m.toFixed(3)}</td><td>${r.risk_bucket}</td><td>${tf}</td>`;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        resultsDiv.appendChild(table);
    });
});