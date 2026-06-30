<script>
window.BookingPayment = window.BookingPayment || {
    formatAmount(amount) {
        return Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    },
    summary(currency, upfrontAmount, totalAmount, upfrontPercent) {
        if (!upfrontAmount) {
            return '';
        }

        const formattedUpfront = `${currency} ${this.formatAmount(upfrontAmount)}`;

        if (totalAmount && upfrontPercent && upfrontPercent < 100) {
            return `{{ __('Pay') }} ${formattedUpfront} {{ __('now') }} (${upfrontPercent}% {{ __('of') }} ${currency} ${this.formatAmount(totalAmount)} {{ __('total') }})`;
        }

        return `{{ __('Amount due:') }} ${formattedUpfront}`;
    },
    buttonLabel(currency, upfrontAmount, actionLabel) {
        if (!upfrontAmount) {
            return actionLabel;
        }

        return `{{ __('Pay') }} ${currency} ${this.formatAmount(upfrontAmount)} & ${actionLabel}`;
    },
};
</script>
