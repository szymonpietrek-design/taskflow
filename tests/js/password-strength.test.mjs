import test from 'node:test';
import assert from 'node:assert/strict';
import { evaluatePasswordStrength } from '../../resources/js/lib/passwordStrength.ts';

test('does not automatically mark a long diverse password weak because of one sequence', () => {
    const result = evaluatePasswordStrength('xY7!zTQ1234567@r');
    assert.equal(result.level, 3);
    assert.equal(result.predictable, true);
    assert.deepEqual(Object.values(result.criteria), [true, true, true, true, true]);
});

test('rewards long non-obvious varied passwords', () => {
    const result = evaluatePasswordStrength('R9!vM3#qL8@zT2$x');
    assert.equal(result.level, 4);
    assert.equal(result.predictable, false);
});

test('does not penalize a long multiword passphrase for missing digits', () => {
    const result = evaluatePasswordStrength('wiszący księżyc zielona latarnia');
    assert.equal(result.level, 4);
    assert.equal(result.criteria.symbol, false);
});

test('does not rate common passwords and repetitive strings as strong', () => {
    assert.equal(evaluatePasswordStrength('Password123!').level, 1);
    assert.equal(evaluatePasswordStrength('aaaaaaaaaaaaaaaa').level, 1);
    assert.equal(evaluatePasswordStrength('1234567890123456').level, 1);
});

test('reports useful criteria and handles empty or short values', () => {
    assert.equal(evaluatePasswordStrength('').level, 0);
    const result = evaluatePasswordStrength('Aa1!x');
    assert.equal(result.level, 1);
    assert.equal(result.criteria.length, false);
    assert.equal(result.criteria.lowercase, true);
    assert.equal(result.criteria.uppercase, true);
    assert.equal(result.criteria.number, true);
    assert.equal(result.criteria.symbol, true);
});

test('detects predictable keyboard and alphabet patterns', () => {
    assert.equal(evaluatePasswordStrength('B8!qwerty2025Stuff').predictable, true);
    assert.equal(evaluatePasswordStrength('Xz4#abcdeTU9').predictable, true);
});
