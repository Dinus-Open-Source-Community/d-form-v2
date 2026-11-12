import { describe, expect, it } from 'vitest';
import { formFillSlotUi } from '@/lib/formFillSlotUi';
import type { IFormFillSlotUiInput } from '@/types/form';

const LINEAR: IFormFillSlotUiInput = {
    variant: 'linear',
    stackIndex: 0,
    fieldHasDescription: true,
    slotHasTitle: true,
    slotIndex: null,
};

describe('formFillSlotUi', () => {
    it('shows subtitle and description for a linear field', () => {
        expect(formFillSlotUi(LINEAR)).toEqual({
            showSubtitle: true,
            showDescription: true,
            headerBorderClass: '',
            cardHeaderSpacingClass: 'gap-1 pb-1.5 pt-3',
            cardContentSpacingClass: 'pb-3 pt-0',
            errorSpacingClass: 'mt-1.5',
        });
    });

    it('hides the subtitle on bundle participant rows', () => {
        const ui = formFillSlotUi({ ...LINEAR, variant: 'bundleParticipant', stackIndex: 0 });

        expect(ui.showSubtitle).toBe(false);
        expect(ui.cardHeaderSpacingClass).toBe('gap-0.5 pt-2.5 pb-0.5');
        expect(ui.cardContentSpacingClass).toBe('pb-2 pt-0');
        expect(ui.errorSpacingClass).toBe('mt-1');
    });

    it('adds a top border and tighter padding for stacked participants', () => {
        const ui = formFillSlotUi({ ...LINEAR, variant: 'bundleParticipant', stackIndex: 1 });

        expect(ui.headerBorderClass).toBe('border-t border-border');
        expect(ui.cardHeaderSpacingClass).toBe('gap-0.5 pt-2 pb-0.5');
    });

    it('shows the description on bundle rows even for participant slots', () => {
        const ui = formFillSlotUi({
            ...LINEAR,
            variant: 'bundleParticipant',
            slotIndex: 2,
        });

        expect(ui.showDescription).toBe(true);
    });

    it('hides the description for non-lead linear slots and for fields without one', () => {
        expect(formFillSlotUi({ ...LINEAR, slotIndex: 1 }).showDescription).toBe(false);
        expect(formFillSlotUi({ ...LINEAR, fieldHasDescription: false }).showDescription).toBe(false);
        expect(formFillSlotUi({ ...LINEAR, slotHasTitle: false }).showSubtitle).toBe(false);
    });
});
