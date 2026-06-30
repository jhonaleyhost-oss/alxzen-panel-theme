import React, { useState, useEffect } from 'react';
import { XIcon, InformationCircleIcon, ExclamationIcon, SpeakerphoneIcon } from '@heroicons/react/solid';
import getAnnouncements, { Announcement } from '@/api/account/getAnnouncements';
import markAnnouncementRead from '@/api/account/markAnnouncementRead';
import tw, { styled } from 'twin.macro';

const Container = styled.div<{ $type: string }>`
    ${tw`relative text-white px-6 py-4 rounded-lg shadow-md mb-6 flex items-start justify-between transition-all duration-300`};
    ${(props) => {
        switch (props.$type) {
            case 'critical':
                return tw`bg-red-600 border border-red-500`;
            case 'warning':
                return tw`bg-yellow-600 border border-yellow-500`;
            case 'promo':
                return tw`bg-purple-600 border border-purple-500`;
            default:
                return tw`bg-blue-600 border border-blue-500`;
        }
    }}
`;

const Content = styled.div`
    ${tw`flex-1 ml-4`}
    & h3 {
        ${tw`font-bold text-lg m-0`}
    }
    & p {
        ${tw`m-0 mt-1 text-sm opacity-90`}
    }
`;

const CloseButton = styled.button`
    ${tw`p-1 rounded-md transition-colors duration-200 hover:bg-white/20`}
`;

interface Props {
    displayLocation: 'dashboard' | 'console';
}

export default ({ displayLocation }: Props) => {
    const [announcements, setAnnouncements] = useState<Announcement[]>([]);
    const [visible, setVisible] = useState<Announcement[]>([]);

    useEffect(() => {
        getAnnouncements().then((data) => {
            const filtered = data.filter((a) => a.target_display.includes(displayLocation));
            setAnnouncements(filtered);
            setVisible(filtered);
        }).catch(console.error);
    }, [displayLocation]);

    const handleDismiss = (id: number) => {
        setVisible((s) => s.filter((a) => a.id !== id));
        markAnnouncementRead(id).catch(console.error);
    };

    if (visible.length === 0) return null;

    return (
        <div className="space-y-4 mb-6">
            {visible.map((announcement) => (
                <Container key={announcement.id} $type={announcement.type}>
                    <div className="flex-shrink-0 mt-1">
                        {announcement.type === 'critical' || announcement.type === 'warning' ? (
                            <ExclamationIcon className="w-6 h-6" />
                        ) : announcement.type === 'promo' ? (
                            <SpeakerphoneIcon className="w-6 h-6" />
                        ) : (
                            <InformationCircleIcon className="w-6 h-6" />
                        )}
                    </div>
                    <Content>
                        <h3>{announcement.title}</h3>
                        <p dangerouslySetInnerHTML={{ __html: announcement.content }} />
                    </Content>
                    <CloseButton onClick={() => handleDismiss(announcement.id)}>
                        <XIcon className="w-5 h-5" />
                    </CloseButton>
                </Container>
            ))}
        </div>
    );
};
