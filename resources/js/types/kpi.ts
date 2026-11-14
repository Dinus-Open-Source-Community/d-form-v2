export type TWeeklyKpiColor = 'primary' | 'success' | 'warning' | 'destructive';

export interface IWeeklyKpiPoint {
    label: string;
    value: number;
}

export interface IWeeklyKpiTrends {
    totalEvents: IWeeklyKpiPoint[];
    activeEvents: IWeeklyKpiPoint[];
    totalRegistrants: IWeeklyKpiPoint[];
    completionRate: IWeeklyKpiPoint[];
}
