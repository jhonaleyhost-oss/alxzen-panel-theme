import http from '@/api/http';

export interface Announcement {
    id: number;
    title: string;
    content: string;
    type: 'info' | 'warning' | 'critical' | 'promo';
    priority: number;
    target_display: string[];
}

export default async (): Promise<Announcement[]> => {
    const { data } = await http.get('/api/client/announcements');

    return (data.data || []).map((datum: any) => datum.attributes);
};
