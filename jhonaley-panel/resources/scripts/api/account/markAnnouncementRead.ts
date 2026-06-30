import http from '@/api/http';

export default async (id: number): Promise<void> => {
    await http.post(`/api/client/announcements/${id}/read`);
};
