import type { IFormFillSlotUi, IFormFillSlotUiInput } from '@/types/form';

/** Vertical rhythm + subtitle/description visibility for one fill field card row. */
export function formFillSlotUi(input: IFormFillSlotUiInput): IFormFillSlotUi {
    const isBundleParticipant = input.variant === 'bundleParticipant';

    return {
        showSubtitle: input.variant === 'linear' && input.slotHasTitle,
        showDescription: input.fieldHasDescription && (isBundleParticipant || input.slotIndex === null),
        headerBorderClass: isBundleParticipant && input.stackIndex > 0 ? 'border-t border-border' : '',
        cardHeaderSpacingClass: isBundleParticipant
            ? input.stackIndex > 0
                ? 'gap-0.5 pt-2 pb-0.5'
                : 'gap-0.5 pt-2.5 pb-0.5'
            : 'gap-1 pb-1.5 pt-3',
        cardContentSpacingClass: isBundleParticipant ? 'pb-2 pt-0' : 'pb-3 pt-0',
        errorSpacingClass: isBundleParticipant ? 'mt-1' : 'mt-1.5',
    };
}
