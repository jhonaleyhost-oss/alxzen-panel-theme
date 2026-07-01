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
    const { data } = await http.get('/api/client/announcements').catch(() => ({ data: { data: [] } }));

    return (data.data || []).map((datum: any) => ({
        ...datum.attributes,
        type: datum.attributes?.type || 'info',
        priority: datum.attributes?.priority || 2,
        target_display: Array.isArray(datum.attributes?.target_display) ? datum.attributes.target_display : ['dashboard'],
    }));
};
