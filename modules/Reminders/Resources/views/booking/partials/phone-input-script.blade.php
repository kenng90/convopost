<script>
window.BookingPhone = {
    init(inputId, initialCountry) {
        const input = document.getElementById(inputId);
        if (! input || ! window.intlTelInput) {
            return null;
        }

        return window.intlTelInput(input, {
            initialCountry: initialCountry || 'ke',
            separateDialCode: true,
            utilsScript: @json(asset('vendor/IntlTelInput/utils.js')),
        });
    },
    digits(iti) {
        if (! iti) {
            return '';
        }

        const number = iti.getNumber();

        if (! number) {
            return '';
        }

        return number.replace(/\D/g, '');
    },
    isValid(iti) {
        const digits = this.digits(iti);

        if (digits.length < 9) {
            return false;
        }

        if (typeof iti.isValidNumber === 'function' && window.intlTelInputUtils) {
            return iti.isValidNumber();
        }

        return true;
    },
};
</script>
