const formatter = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatter.format(new Date(value));
}
