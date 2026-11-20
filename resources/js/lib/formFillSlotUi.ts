import type { IFormFillSlotUi, IFormFillSlotUiInput } from '@/types/form';

/** Vertical rhythm + subtitle/description visibility for one fill field card row. */
export function formFillSlotUi(input: IFormFillSlotUiInput): IFormFillSlotUi {
    const isBundleParticipant = input.variant === 'bundleParticipant';
    const showSubtitle = input.variant === 'linear' && input.slotHasTitle;

    return {
        showSubtitle,
        showDescription: input.fieldHasDescription && (isBundleParticipant || input.slotIndex === null),
        headerBorderClass: isBundleParticipant && input.stackIndex > 0 ? 'border-t border-border' : '',
        cardHeaderSpacingClass: isBundleParticipant
            ? input.stackIndex > 0
                ? 'gap-0.5 pt-2 pb-0.5'
                : 'gap-0.5 pt-2.5 pb-0.5'
            : showSubtitle
              ? 'gap-2 pt-3 pb-1.5'
              : 'gap-0.5 pt-2.5 pb-1',
        cardContentSpacingClass: isBundleParticipant ? 'pb-2 pt-0' : 'pb-2.5 pt-0',
        errorSpacingClass: isBundleParticipant ? 'mt-1' : 'mt-1.5',
    };
}
