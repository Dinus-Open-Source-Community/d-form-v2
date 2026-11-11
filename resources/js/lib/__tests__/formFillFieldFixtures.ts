/**
 * Test-only field factory shared by fill-flow unit tests. Not imported by runtime code;
 * `__tests__/` is excluded from coverage and from the production bundle.
 */
export function makeFormField(overrides: Partial<IFormField> & { name: string }): IFormField {
    return {
        id: `field-${overrides.name}`,
        type: 'input',
        label: overrides.name,
        description: null,
        order: 0,
        metadata: {},
        ...overrides,
    };
}
