import QrScanner from 'qr-scanner';

const qrScannerInstances = new WeakMap();
const singletonScannerInstances = new WeakMap();
const qrScannerSounds = {
    scan: new Audio('/sfx/scan.mp3'),
    admit: new Audio('/sfx/admit.mp3'),
    priority: new Audio('/sfx/priority.mp3'),
    error: new Audio('/sfx/error.mp3'),
};
let activeQrScannerSound = null;

Object.values(qrScannerSounds).forEach((sound) => {
    sound.preload = 'auto';
    sound.addEventListener('ended', () => {
        if (activeQrScannerSound === sound) {
            activeQrScannerSound = null;
        }
    });
});

function shouldIgnoreQrDecodeError(error) {
    const message = String(error?.message ?? error ?? '').toLowerCase();

    return error?.name === 'NoQrCodeFoundError' || message.includes('no qr code found');
}

function playQrScannerSound(name) {
    const sound = qrScannerSounds[name];

    if (!sound) {
        return Promise.resolve();
    }

    if (activeQrScannerSound && activeQrScannerSound !== sound) {
        activeQrScannerSound.pause();
        activeQrScannerSound.currentTime = 0;
        activeQrScannerSound = null;
    }

    sound.pause();
    sound.currentTime = 0;
    activeQrScannerSound = sound;

    return sound.play().catch(() => {
        if (activeQrScannerSound === sound) {
            activeQrScannerSound = null;
        }

        // Audio playback can be blocked until the browser considers the page interacted with.
    });
}

async function activateQrScannerSoundEffects() {
    const names = Object.keys(qrScannerSounds);

    for (const name of names) {
        await playQrScannerSound(name);
        await delay(150);
    }
}

function delay(milliseconds) {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function isTimeoutTestQrCode(qrCode) {
    try {
        const url = new URL(qrCode);
        const ticketValue = String(url.searchParams.get('tkt') ?? '').trim();

        return /^TEST_TIMEOUT_\d{4}$/i.test(ticketValue);
    } catch {
        return false;
    }
}

async function verifyGoldenTicketQrCode(verifyUrl, qrCode) {
    if (isTimeoutTestQrCode(qrCode)) {
        const timeoutError = new Error('Scan timeout');
        timeoutError.code = 'SCAN_TIMEOUT';

        throw timeoutError;
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort('timeout'), 5000);

    try {
        const response = await fetch(verifyUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ qr_code: qrCode }),
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Ticket verification failed with status ${response.status}.`);
        }

        return response.json();
    } catch (error) {
        if (error === 'timeout' || error?.name === 'AbortError') {
            const timeoutError = new Error('Scan timeout');
            timeoutError.code = 'SCAN_TIMEOUT';
            throw timeoutError;
        }

        throw error;
    } finally {
        window.clearTimeout(timeoutId);
    }
}

function describeQrScanResult(payload) {
    const status = String(payload?.status ?? 'INVALID');
    const firstName = String(payload?.first_name ?? '').trim();
    const message = String(payload?.message ?? '').trim();

    switch (status) {
        case 'OK':
            return {
                tone: 'success',
                heading: 'OK',
                name: firstName,
                detail: '',
                requiresAcknowledgement: false,
            };
        case 'OK_GROUP_ZERO':
            return {
                tone: 'priority',
                heading: 'GROUP ZERO',
                name: firstName,
                detail: '',
                requiresAcknowledgement: false,
            };
        case 'REVOKED':
            return {
                tone: 'error',
                heading: 'REVOKED',
                name: firstName,
                detail: 'Please send this volunteer to a coordinator for assistance.',
                requiresAcknowledgement: false,
            };
        case 'ALREADY_SCANNED':
            return {
                tone: 'error',
                heading: 'ALREADY SCANNED',
                name: firstName,
                detail: message !== '' && message !== status ? message : '',
                requiresAcknowledgement: false,
            };
        default:
            return {
                tone: 'error',
                heading: 'INVALID',
                name: '',
                detail: 'This ticket is not recognized.',
                requiresAcknowledgement: false,
            };
    }
}

function applyQrScannerTone(root, tone) {
    root.classList.remove('bg-gray-950', 'bg-emerald-700', 'bg-purple-700', 'bg-red-700', 'bg-yellow-400', 'text-white', 'text-gray-950');

    switch (tone) {
        case 'success':
            root.classList.add('bg-emerald-700', 'text-white');
            break;
        case 'priority':
            root.classList.add('bg-purple-700', 'text-white');
            break;
        case 'error':
            root.classList.add('bg-red-700', 'text-white');
            break;
        case 'timeout':
            root.classList.add('bg-yellow-400', 'text-gray-950');
            break;
        default:
            root.classList.add('bg-gray-950', 'text-white');
            break;
    }
}

function updateQrScannerUi(root, options = {}) {
    const {
        scanning = false,
        tone = 'idle',
        heading = scanning ? 'SCANNER ACTIVE' : 'READY',
        name = '',
        detail = '',
        requiresAcknowledgement = false,
    } = options;

    const toggleButton = root.querySelector('[data-qr-toggle]');
    const toggleIcon = root.querySelector('[data-qr-toggle-icon]');
    const toggleLabel = root.querySelector('[data-qr-toggle-label]');
    const feedbackHeading = root.querySelector('[data-qr-feedback-heading]');
    const feedbackName = root.querySelector('[data-qr-feedback-name]');
    const feedbackDetail = root.querySelector('[data-qr-feedback-detail]');
    const acknowledgeButton = root.querySelector('[data-qr-acknowledge]');

    root.dataset.scanning = scanning ? 'true' : 'false';
    applyQrScannerTone(root, tone);

    if (toggleButton) {
        toggleButton.classList.toggle('bg-emerald-500', scanning);
        toggleButton.classList.toggle('text-white', scanning);
        toggleButton.classList.toggle('hover:bg-emerald-400', scanning);
        toggleButton.classList.toggle('bg-gray-200', !scanning);
        toggleButton.classList.toggle('text-gray-900', !scanning);
        toggleButton.classList.toggle('hover:bg-white', !scanning);
        toggleButton.setAttribute('aria-pressed', scanning ? 'true' : 'false');
        toggleButton.setAttribute('title', scanning ? 'Stop scanner' : 'Start scanner');
    }

    if (toggleIcon) {
        toggleIcon.classList.toggle('fa-power-off', !scanning);
        toggleIcon.classList.toggle('fa-stop', scanning);
    }

    if (toggleLabel) {
        toggleLabel.textContent = scanning ? 'Stop scanner' : 'Start scanner';
    }

    if (feedbackHeading) {
        feedbackHeading.textContent = heading;
    }

    if (feedbackName) {
        feedbackName.textContent = name;
        feedbackName.classList.toggle('hidden', name === '');
    }

    if (feedbackDetail) {
        feedbackDetail.textContent = detail;
        feedbackDetail.classList.toggle('hidden', detail === '');
    }
}

function destroyQrScanner(root) {
    const instance = qrScannerInstances.get(root);

    if (!instance) {
        return;
    }

    instance.toggleButton.removeEventListener('click', instance.handleToggle);
    instance.activateSfxButton?.removeEventListener('click', instance.handleActivateSfx);
    instance.scanner.destroy();
    qrScannerInstances.delete(root);
}

function initQrScanner(root) {
    if (qrScannerInstances.has(root)) {
        return;
    }

    const video = root.querySelector('[data-qr-video]');
    const toggleButton = root.querySelector('[data-qr-toggle]');
    const activateSfxButton = root.querySelector('[data-qr-activate-sfx]');
    const acknowledgeButton = root.querySelector('[data-qr-acknowledge]');

    if (!video || !toggleButton) {
        return;
    }

    let isStarting = false;
    let isVerifying = false;
    let requiresAcknowledgement = false;
    let lastScannedData = null;
    let feedbackResetToken = 0;
    const verifyUrl = root.dataset.qrVerifyUrl ?? '/golden-tickets/scan';

    const scheduleReadyReset = (token) => {
        window.setTimeout(() => {
            if (feedbackResetToken !== token || isVerifying || lastScannedData !== null) {
                return;
            }

            updateQrScannerUi(root, {
                scanning: root.dataset.scanning === 'true',
                tone: 'idle',
                heading: root.dataset.scanning === 'true' ? 'SCANNER ACTIVE' : 'READY',
                detail: '',
            });
        }, 10000);
    };

    const scanner = new QrScanner(
        video,
        async (result) => {
            const scannedData = typeof result === 'string' ? result : result?.data ?? null;

            if (!scannedData || scannedData === lastScannedData || isVerifying) {
                return;
            }

            lastScannedData = scannedData;
            isVerifying = true;
            feedbackResetToken += 1;
            const currentResetToken = feedbackResetToken;

            const soundDelay = delay(750);

            await playQrScannerSound('scan');
            console.log('Scanned QR code data:', scannedData);
            updateQrScannerUi(root, {
                scanning: true,
                tone: 'idle',
                heading: 'VERIFYING',
                detail: 'Checking ticket...',
            });

            try {
                const payload = await verifyGoldenTicketQrCode(verifyUrl, scannedData);
                const status = String(payload?.status ?? 'INVALID');
                const description = describeQrScanResult(payload);

                console.log('Golden Ticket scan result:', payload);
                await soundDelay;

                if (status === 'OK') {
                    await playQrScannerSound('admit');
                } else if (status === 'OK_GROUP_ZERO') {
                    await playQrScannerSound('priority');
                } else {
                    await playQrScannerSound('error');
                }

                requiresAcknowledgement = description.requiresAcknowledgement;
                updateQrScannerUi(root, {
                    scanning: true,
                    ...description,
                });

                scheduleReadyReset(currentResetToken);
            } catch (error) {
                console.error('Unable to verify scanned QR code:', error);
                await soundDelay;
                await playQrScannerSound('error');

                if (error?.code === 'SCAN_TIMEOUT') {
                    lastScannedData = null;
                    requiresAcknowledgement = false;

                    updateQrScannerUi(root, {
                        scanning: true,
                        tone: 'timeout',
                        heading: 'Scan Again - Timeout',
                        detail: 'Please try the same ticket again.',
                    });

                    scheduleReadyReset(currentResetToken);
                }
            } finally {
                window.setTimeout(() => {
                    if (lastScannedData === scannedData) {
                        lastScannedData = null;
                    }
                }, 3000);

                isVerifying = false;
            }
        },
        {
            preferredCamera: 'environment',
            highlightScanRegion: true,
            highlightCodeOutline: true,
            returnDetailedScanResult: true,
            onDecodeError: (error) => {
                if (shouldIgnoreQrDecodeError(error)) {
                    return;
                }

                console.debug('QR scanner decode error:', error);
            },
        },
    );

    const handleAcknowledge = () => {
        requiresAcknowledgement = false;
        lastScannedData = null;

        updateQrScannerUi(root, {
            scanning: root.dataset.scanning === 'true',
            tone: 'idle',
            heading: root.dataset.scanning === 'true' ? 'SCANNER ACTIVE' : 'READY',
            detail: '',
        });
    };

    const handleActivateSfx = async () => {
        if (!activateSfxButton) {
            return;
        }

        activateSfxButton.disabled = true;
        await activateQrScannerSoundEffects();
        activateSfxButton.classList.add('hidden');
    };

    const handleToggle = async () => {
        if (root.dataset.scanning === 'true') {
            scanner.stop();
            lastScannedData = null;
            isVerifying = false;
            requiresAcknowledgement = false;
            updateQrScannerUi(root, {
                scanning: false,
                tone: 'idle',
                heading: 'READY',
                detail: 'Scanner stopped.',
            });

            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            console.warn('QR scanner requires a secure browser context with camera access.');
            updateQrScannerUi(root, {
                scanning: false,
                tone: 'error',
                heading: 'CAMERA UNAVAILABLE',
                detail: 'Camera requires HTTPS or a supported localhost browser.',
            });

            return;
        }

        if (isStarting) {
            return;
        }

        isStarting = true;
        updateQrScannerUi(root, {
            scanning: false,
            tone: 'idle',
            heading: 'STARTING',
            detail: 'Starting camera...',
        });

        try {
            await scanner.start();
            updateQrScannerUi(root, {
                scanning: true,
                tone: 'idle',
                heading: 'SCANNER ACTIVE',
                detail: '',
            });
        } catch (error) {
            console.error('Unable to start QR scanner:', error);
            updateQrScannerUi(root, {
                scanning: false,
                tone: 'error',
                heading: 'CAMERA ERROR',
                detail: 'Unable to access camera. Check permissions and try again.',
            });
        } finally {
            isStarting = false;
        }
    };

    toggleButton.addEventListener('click', handleToggle);
    activateSfxButton?.addEventListener('click', handleActivateSfx);

    qrScannerInstances.set(root, {
        scanner,
        toggleButton,
        handleToggle,
        activateSfxButton,
        handleActivateSfx,
    });

    updateQrScannerUi(root, {
        scanning: false,
        tone: 'idle',
        heading: 'READY',
        detail: '',
    });
}

function initQrScannerPages() {
    document.querySelectorAll('[data-qr-scanner-root]').forEach(initQrScanner);
}

function cleanupQrScannerPages() {
    document.querySelectorAll('[data-qr-scanner-root]').forEach(destroyQrScanner);
}

function verifySingletonScannerInput(verifyUrl, input, dataSource) {
    if (isTimeoutTestQrCode(input)) {
        const timeoutError = new Error('Scan timeout');
        timeoutError.code = 'SCAN_TIMEOUT';

        return Promise.reject(timeoutError);
    }

    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort('timeout'), 5000);

    return fetch(verifyUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            qr_code: input,
            data_source: dataSource,
        }),
        signal: controller.signal,
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Ticket verification failed with status ${response.status}.`);
            }

            return response.json();
        })
        .catch((error) => {
            if (error === 'timeout' || error?.name === 'AbortError') {
                const timeoutError = new Error('Scan timeout');
                timeoutError.code = 'SCAN_TIMEOUT';
                throw timeoutError;
            }

            throw error;
        })
        .finally(() => {
            window.clearTimeout(timeoutId);
        });
}

function applySingletonScannerTone(root, tone) {
    root.classList.remove('bg-gray-950', 'bg-emerald-700', 'bg-purple-700', 'bg-red-700', 'bg-yellow-400', 'text-white', 'text-gray-950');

    switch (tone) {
        case 'success':
            root.classList.add('bg-emerald-700', 'text-white');
            break;
        case 'priority':
            root.classList.add('bg-purple-700', 'text-white');
            break;
        case 'error':
            root.classList.add('bg-red-700', 'text-white');
            break;
        case 'timeout':
            root.classList.add('bg-yellow-400', 'text-gray-950');
            break;
        default:
            root.classList.add('bg-gray-950', 'text-white');
            break;
    }
}

function updateSingletonScannerUi(root, options = {}) {
    const {
        tone = 'idle',
        heading = 'READY',
        name = '',
        detail = '',
    } = options;

    const feedbackHeading = root.querySelector('[data-singleton-feedback-heading]');
    const feedbackName = root.querySelector('[data-singleton-feedback-name]');
    const feedbackDetail = root.querySelector('[data-singleton-feedback-detail]');

    applySingletonScannerTone(root, tone);

    if (feedbackHeading) {
        feedbackHeading.textContent = heading;
    }

    if (feedbackName) {
        feedbackName.textContent = name;
        feedbackName.classList.toggle('hidden', name === '');
    }

    if (feedbackDetail) {
        feedbackDetail.textContent = detail;
        feedbackDetail.classList.toggle('hidden', detail === '');
    }
}

function renderSingletonScannerLog(root, entries) {
    const list = root.querySelector('[data-singleton-log]');

    if (!list) {
        return;
    }

    if (entries.length === 0) {
        list.innerHTML = '<li class="rounded-lg border border-white/40 bg-white/70 px-3 py-2 text-gray-900">No scans yet.</li>';
        return;
    }

    list.innerHTML = entries
        .map((entry) => {
            const escapedInput = entry.input
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
            const escapedResult = entry.result
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');

            return `<li class="rounded-lg border border-white/40 bg-white/70 px-3 py-2 text-gray-900">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold">${escapedResult}</span>
                    <span class="text-xs text-gray-700">${entry.time}</span>
                </div>
                <p class="mt-1 truncate text-xs text-gray-800" title="${escapedInput}">${escapedInput}</p>
            </li>`;
        })
        .join('');
}

function destroySingletonScanner(root) {
    const instance = singletonScannerInstances.get(root);

    if (!instance) {
        return;
    }

    instance.input.removeEventListener('keydown', instance.handleKeydown);
    instance.activateSfxButton?.removeEventListener('click', instance.handleActivateSfx);
    singletonScannerInstances.delete(root);
}

function initSingletonScanner(root) {
    if (singletonScannerInstances.has(root)) {
        return;
    }

    const input = root.querySelector('[data-singleton-input]');
    const activateSfxButton = root.parentElement?.querySelector('[data-singleton-activate-sfx]') ?? null;

    if (!input) {
        return;
    }

    const verifyUrl = root.dataset.singletonVerifyUrl ?? '/golden-tickets/scan';
    const dataSource = root.dataset.singletonDataSource ?? 'Nadamoo Live Scanner';
    let isVerifying = false;
    let feedbackResetToken = 0;
    let recentEntries = [];

    const pushLogEntry = (inputValue, result) => {
        recentEntries = [
            {
                input: inputValue,
                result,
                time: new Date().toLocaleTimeString(),
            },
            ...recentEntries,
        ].slice(0, 5);

        renderSingletonScannerLog(root, recentEntries);
    };

    const scheduleReadyReset = (token) => {
        window.setTimeout(() => {
            if (feedbackResetToken !== token || isVerifying) {
                return;
            }

            updateSingletonScannerUi(root, {
                tone: 'idle',
                heading: 'READY',
                name: '',
                detail: '',
            });
        }, 10000);
    };

    const handleActivateSfx = async () => {
        if (!activateSfxButton) {
            return;
        }

        activateSfxButton.disabled = true;
        await activateQrScannerSoundEffects();
        activateSfxButton.classList.add('hidden');
    };

    const handleKeydown = async (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if (isVerifying) {
            return;
        }

        const inputValue = String(input.value ?? '').trim();

        if (inputValue === '') {
            return;
        }

        isVerifying = true;
        feedbackResetToken += 1;
        const currentResetToken = feedbackResetToken;
        const soundDelay = delay(750);

        input.value = '';

        await playQrScannerSound('scan');
        updateSingletonScannerUi(root, {
            tone: 'idle',
            heading: 'VERIFYING',
            detail: 'Checking ticket...',
        });

        try {
            const payload = await verifySingletonScannerInput(verifyUrl, inputValue, dataSource);
            const status = String(payload?.status ?? 'INVALID');
            const description = describeQrScanResult(payload);

            await soundDelay;

            if (status === 'OK') {
                await playQrScannerSound('admit');
            } else if (status === 'OK_GROUP_ZERO') {
                await playQrScannerSound('priority');
            } else {
                await playQrScannerSound('error');
            }

            updateSingletonScannerUi(root, description);
            pushLogEntry(inputValue, `${description.heading}${description.name ? ` - ${description.name}` : ''}`);
            scheduleReadyReset(currentResetToken);
        } catch (error) {
            await soundDelay;
            await playQrScannerSound('error');

            if (error?.code === 'SCAN_TIMEOUT') {
                updateSingletonScannerUi(root, {
                    tone: 'timeout',
                    heading: 'Scan Again - Timeout',
                    detail: 'Please try the same ticket again.',
                });
                pushLogEntry(inputValue, 'TIMEOUT');
                scheduleReadyReset(currentResetToken);
            } else {
                updateSingletonScannerUi(root, {
                    tone: 'error',
                    heading: 'ERROR',
                    detail: 'Unable to verify this scan right now.',
                });
                pushLogEntry(inputValue, 'ERROR');
                scheduleReadyReset(currentResetToken);
                console.error('Singleton scanner verification failed:', error);
            }
        } finally {
            isVerifying = false;
            input.focus();
        }
    };

    input.addEventListener('keydown', handleKeydown);
    activateSfxButton?.addEventListener('click', handleActivateSfx);

    singletonScannerInstances.set(root, {
        input,
        handleKeydown,
        activateSfxButton,
        handleActivateSfx,
    });

    updateSingletonScannerUi(root, {
        tone: 'idle',
        heading: 'READY',
        detail: '',
    });
    renderSingletonScannerLog(root, []);
    input.focus();
}

function initSingletonScannerPages() {
    document.querySelectorAll('[data-singleton-scanner-root]').forEach(initSingletonScanner);
}

function cleanupSingletonScannerPages() {
    document.querySelectorAll('[data-singleton-scanner-root]').forEach(destroySingletonScanner);
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function (error) {
        console.warn('Service worker registration failed:', error);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initQrScannerPages();
    initSingletonScannerPages();
});
document.addEventListener('livewire:navigated', () => {
    initQrScannerPages();
    initSingletonScannerPages();
});
document.addEventListener('livewire:navigating', () => {
    cleanupQrScannerPages();
    cleanupSingletonScannerPages();
});
window.addEventListener('beforeunload', () => {
    cleanupQrScannerPages();
    cleanupSingletonScannerPages();
});
