/**
 * Lightweight, indicative password feedback for the admin form.
 * Not an entropy estimate, a breach-list check, or a replacement for server validation.
 */
export type PasswordCriteria = {
    length: boolean;
    lowercase: boolean;
    uppercase: boolean;
    number: boolean;
    symbol: boolean;
};

export type PasswordStrengthResult = {
    level: 0 | 1 | 2 | 3 | 4;
    criteria: PasswordCriteria;
    predictable: boolean;
};

const KEYBOARD_PATTERNS = /qwerty|asdfgh|zxcvbn|qazwsx|zaq1|1qaz|2wsx|wsx2/i;
const COMMON_PASSWORD = /^(?:password|passw0rd|qwerty|admin|welcome|haslo|hasło|letmein)(?:[\d!@#$%^&*._-]*)$/iu;

function hasOrderedSequence(password: string): boolean {
    const value = password.toLowerCase();
    let ascending = 1;
    let descending = 1;

    for (let i = 1; i < value.length; i++) {
        const previous = value.charCodeAt(i - 1);
        const current = value.charCodeAt(i);
        const bothDigits = previous >= 48 && previous <= 57 && current >= 48 && current <= 57;
        const bothLetters = previous >= 97 && previous <= 122 && current >= 97 && current <= 122;

        ascending = (bothDigits || bothLetters) && current === previous + 1 ? ascending + 1 : 1;
        descending = (bothDigits || bothLetters) && current === previous - 1 ? descending + 1 : 1;
        if (ascending >= 5 || descending >= 5) return true;
    }

    return false;
}

export function evaluatePasswordStrength(password: string): PasswordStrengthResult {
    const length = [...password].length;
    const criteria: PasswordCriteria = {
        length: length >= 12,
        lowercase: /\p{Ll}/u.test(password),
        uppercase: /\p{Lu}/u.test(password),
        number: /\p{Nd}/u.test(password),
        symbol: /[^\p{L}\p{N}\s]/u.test(password),
    };
    if (!length) return { level: 0, criteria, predictable: false };

    const groupCount = Number(criteria.lowercase) + Number(criteria.uppercase) + Number(criteria.number) + Number(criteria.symbol);
    const wordCount = password.trim().split(/\s+/u).filter(Boolean).length;
    const longPassphrase = length >= 24 && wordCount >= 3;

    let score = length < 10 ? 1 : length < 12 ? 2 : length < 16 ? 3 : 4;
    if (longPassphrase) score = 4;
    if (groupCount <= 1 && !longPassphrase) score = Math.min(score, length >= 24 ? 2 : 1);
    if (groupCount === 2 && !longPassphrase) score = Math.min(score, length >= 16 ? 3 : 2);

    const predictable = hasOrderedSequence(password)
        || KEYBOARD_PATTERNS.test(password)
        || /(.)\1{3,}/u.test(password)
        || /(.{2,4})\1{2,}/u.test(password);

    if (COMMON_PASSWORD.test(password)) score = 1;
    else if (predictable) score = Math.max(1, score - 1);

    return { level: score as PasswordStrengthResult['level'], criteria, predictable };
}
