import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw, { styled } from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import AnnounceBar from '@/components/elements/AnnounceBar';

const RootContainer = styled.div`
    ${tw`w-full max-w-[1400px] mx-auto p-4 md:p-8`}
`;

const HeaderSection = styled.div`
    ${tw`flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6`}
`;

const TitleBox = styled.div`
    ${tw`text-left`} /* Force Left Alignment */
`;

const Title = styled.h1`
    ${tw`text-5xl md:text-6xl font-black tracking-tighter text-white mb-2`}
    span {
        ${tw`text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-500`}
    }
`;

const SubTitle = styled.p`
    ${tw`text-gray-400 font-medium text-lg`}
`;

const FilterBar = styled.div`
    ${tw`flex items-center gap-4 bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl px-6 py-3`}
`;

const Grid = styled.div`
    ${tw`grid grid-cols-1 lg:grid-cols-2 gap-6 pb-20`}
`;

const FadeInEntry = styled.div<{ $delay: number }>`
    animation: fadeInUp 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    opacity: 0;
    transform: translateY(20px);
    animation-delay: ${props => props.$delay}ms;

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;

const SkeletonCard = styled.div`
    ${tw`w-full h-[240px] rounded-[24px] bg-white/5 animate-pulse border border-white/5`}
`;

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');
    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    useEffect(() => { setPage(1); }, [showOnlyAdmin]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            <NavigationBar />
            <RootContainer>
                <HeaderSection>
                    <TitleBox>
                        <Title>Hello, <span>{username}</span></Title>
                        <SubTitle>System online. Waiting for instructions.</SubTitle>
                    </TitleBox>

                    {rootAdmin && (
                        <FilterBar>
                            <span css={tw`text-xs font-bold text-gray-400 uppercase tracking-wider`}>Admin Mode</span>
                            <Switch
                                name={'show_all_servers'}
                                defaultChecked={showOnlyAdmin}
                                onChange={() => setShowOnlyAdmin((s) => !s)}
                            />
                        </FilterBar>
                    )}
                </HeaderSection>
                
                <AnnounceBar displayLocation="dashboard" />

                {!servers ? (
                    <Grid>
                        {[1, 2, 3, 4].map(i => <SkeletonCard key={i} />)}
                    </Grid>
                ) : (
                    <Pagination data={servers} onPageSelect={setPage}>
                        {({ items }) => (
                            items.length > 0 ? (
                                <Grid>
                                    {items.map((server, index) => (
                                        <FadeInEntry key={server.uuid} $delay={index * 100}>
                                            <ServerRow server={server} />
                                        </FadeInEntry>
                                    ))}
                                </Grid>
                            ) : (
                                <div css={tw`col-span-2 py-32 text-center bg-white/5 rounded-[3rem] border border-dashed border-white/10`}>
                                    <p css={tw`text-2xl text-gray-500 font-bold`}>No Servers Found</p>
                                </div>
                            )
                        )}
                    </Pagination>
                )}
            </RootContainer>
        </PageContentBlock>
    );
};