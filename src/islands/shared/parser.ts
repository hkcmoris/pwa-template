export const parseNumber = (value: string | undefined): number => {
    if (!value) {
        return 0;
    }

    const parsed = Number.parseInt(value, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
};
