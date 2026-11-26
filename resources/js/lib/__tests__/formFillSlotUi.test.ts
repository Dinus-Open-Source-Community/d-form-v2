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
            cardHeaderSpacingClass: 'gap-1 pt-4 pb-0',
            cardContentSpacingClass: 'pt-3 pb-5',
            errorSpacingClass: 'mt-1.5',
        });
    });

    it('hides the subtitle on bundle participant rows', () => {
        const ui = formFillSlotUi({ ...LINEAR, variant: 'bundleParticipant', stackIndex: 0 });

        expect(ui.showSubtitle).toBe(false);
        expect(ui.cardHeaderSpacingClass).toBe('gap-1 pt-3 pb-0');
        expect(ui.cardContentSpacingClass).toBe('pt-2 pb-3');
        expect(ui.errorSpacingClass).toBe('mt-1');
    });

    it('adds a top border and tighter padding for stacked participants', () => {
        const ui = formFillSlotUi({ ...LINEAR, variant: 'bundleParticipant', stackIndex: 1 });

        expect(ui.headerBorderClass).toBe('border-t border-border');
        expect(ui.cardHeaderSpacingClass).toBe('gap-1 pt-2.5 pb-0');
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
