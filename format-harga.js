document.querySelectorAll('[data-price-input]').forEach(input => {
    input.addEventListener('input', () => {
        const cursor = input.selectionStart ?? input.value.length;
        const rawBeforeCursor = input.value.slice(0, cursor);
        const digitsBeforeCursor = rawBeforeCursor.replace(/\D/g, '').length;
        const fractionDigitsBeforeCursor = rawBeforeCursor.includes(',')
            ? rawBeforeCursor.slice(rawBeforeCursor.indexOf(',') + 1).replace(/\D/g, '').length
            : 0;
        const raw = input.value.replace(/[^\d,]/g, '');
        const separator = raw.indexOf(',');
        const integerPart = (separator === -1 ? raw : raw.slice(0, separator)).replace(/^0+(?=\d)/, '');
        const fractionPart = separator === -1 ? null : raw.slice(separator + 1).replace(/,/g, '').slice(0, 2);
        const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = groupedInteger + (fractionPart === null ? '' : `,${fractionPart}`);

        let nextCursor;
        if (rawBeforeCursor.includes(',') && separator !== -1) {
            nextCursor = groupedInteger.length + 1 + Math.min(fractionDigitsBeforeCursor, fractionPart.length);
        } else {
            nextCursor = 0;
            let countedDigits = 0;
            while (nextCursor < groupedInteger.length && countedDigits < digitsBeforeCursor) {
                if (/\d/.test(groupedInteger[nextCursor])) countedDigits++;
                nextCursor++;
            }
        }
        input.setSelectionRange(nextCursor, nextCursor);
    });
});
