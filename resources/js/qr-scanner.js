/**
 * Camera scanner for the gym's check-in QR (member dashboard). Opened by the "scan-qr" event with the action to
 * call ("checkIn" or "checkOut"). Uses the browser's own QR reader where there is one, otherwise jsQR, which is
 * loaded only then. Frames never leave the phone; only the code that was read is sent.
 */
const MAX_WIDTH = 640;
const EVERY_MS = 150;

const reader = async () => {
    if ('BarcodeDetector' in window && (await BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        return async (canvas) => (await detector.detect(canvas))[0]?.rawValue;
    }
    const { default: jsQR } = await import('jsqr');
    return async (canvas) => {
        const { data, width, height } = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
        return jsQR(data, width, height, { inversionAttempts: 'dontInvert' })?.data;
    };
};

window.qrScanner = () => ({
    action: 'checkIn',
    state: 'starting', // starting | scanning | sending | failed
    message: '',
    run: 0, // a newer run stops the older one
    stream: null,

    async open(action) {
        this.action = action;
        this.$refs.dialog.showModal();
        await this.start();
    },

    async start() {
        this.stop();
        const run = this.run;
        this.state = 'starting';
        this.message = '';
        if (!navigator.mediaDevices?.getUserMedia) {
            this.fail('The camera only works on a secure (https) page in an up-to-date browser. Please ask the front desk to check you in.');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            if (run !== this.run) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            this.stream = stream;
            this.$refs.video.srcObject = stream;
            await this.$refs.video.play();
            const read = await reader();
            if (run !== this.run) return;
            this.state = 'scanning';
            this.look(run, read);
        } catch (error) {
            if (run !== this.run) return;
            this.fail(
                {
                    NotAllowedError: 'Camera access is blocked. Allow the camera for this site in your browser settings, then try again.',
                    NotFoundError: 'No camera was found on this device. Please ask the front desk to check you in.',
                    NotReadableError: 'Another app is using the camera. Close it and try again.',
                }[error.name] ?? 'The scanner could not start. Try again, or ask the front desk to check you in.',
            );
        }
    },

    async look(run, read) {
        const { video } = this.$refs;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        while (run === this.run) {
            if (video.videoWidth) {
                const scale = Math.min(1, MAX_WIDTH / video.videoWidth);
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const text = await read(canvas).catch(() => null);
                if (run !== this.run) return;
                if (text?.includes('checkin=')) {
                    this.send(text);
                    return;
                }
                this.message = text ? "That isn't a check-in code. Scan the one at your gym's front desk." : this.message;
            }
            await new Promise((resolve) => setTimeout(resolve, EVERY_MS));
        }
    },

    async send(text) {
        this.stop();
        this.state = 'sending';
        this.message = '';
        navigator.vibrate?.(60);
        try {
            await this.$wire[this.action](text);
            this.$refs.dialog.close();
        } catch {
            this.fail('Could not reach the server. Check your connection and try again.');
        }
    },

    fail(message) {
        this.stop();
        this.state = 'failed';
        this.message = message;
    },

    stop() {
        this.run++;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    },

    destroy() {
        this.stop();
    },
});
