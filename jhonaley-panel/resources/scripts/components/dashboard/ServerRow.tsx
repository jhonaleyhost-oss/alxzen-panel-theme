import React, { memo, useEffect, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faMicrochip, faMemory, faHdd, faNetworkWired, faTerminal, faSignal } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip } from '@/lib/formatters';
import tw, { styled } from 'twin.macro';
import Spinner from '@/components/elements/Spinner';
import isEqual from 'react-fast-compare';
import { motion } from 'framer-motion';

const CardWrapper = styled(motion(Link))<{ $status: string }>`
    ${tw`relative block w-full rounded-[24px] overflow-hidden`}
    background: linear-gradient(145deg, rgba(20, 20, 25, 0.9), rgba(10, 10, 12, 0.95));
    
    border: 1px solid ${({ $status }) => 
        $status === 'running' ? 'rgba(34, 197, 94, 0.2)' : 
        $status === 'starting' ? 'rgba(96, 165, 250, 0.3)' :
        $status === 'offline' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(234, 179, 8, 0.3)'};
        
    box-shadow: ${({ $status }) => 
        $status === 'running' ? '0 0 20px -5px rgba(34, 197, 94, 0.15)' : 
        $status === 'starting' ? '0 0 25px -2px rgba(96, 165, 250, 0.25)' :
        $status === 'offline' ? '0 0 15px -5px rgba(0, 0, 0, 0.5)' : '0 0 20px -5px rgba(234, 179, 8, 0.15)'};
    
    transition: box-shadow 0.5s ease, border-color 0.5s ease;

    &:hover {
        box-shadow: ${({ $status }) => 
            $status === 'running' ? '0 20px 40px -5px rgba(34, 197, 94, 0.25)' : 
            $status === 'starting' ? '0 20px 40px -5px rgba(96, 165, 250, 0.35)' :
            $status === 'offline' ? '0 20px 40px -5px rgba(239, 68, 68, 0.15)' : '0 20px 40px -5px rgba(234, 179, 8, 0.25)'};
        border-color: ${({ $status }) => 
            $status === 'running' ? 'rgba(34, 197, 94, 0.5)' : 
            $status === 'starting' ? 'rgba(96, 165, 250, 0.6)' :
            $status === 'offline' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(234, 179, 8, 0.5)'};
        
        /* Highlight decoration */
        &::before {
            opacity: 1;
        }
    }


    &::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        background: ${({ $status }) => 
            $status === 'running' ? 'linear-gradient(90deg, transparent, #4ade80, transparent)' : 
            $status === 'offline' ? 'linear-gradient(90deg, transparent, #f87171, transparent)' : 'linear-gradient(90deg, transparent, #facc15, transparent)'};
        opacity: ${({ $status }) => $status === 'running' ? '0.5' : '0'};
        transition: opacity 0.5s ease;
    }
`;

const Header = styled.div`
    ${tw`p-6 flex items-start justify-between border-b border-white/5 bg-white/[0.02]`}
`;

const ServerName = styled.h3`
    ${tw`text-xl font-bold text-white tracking-tight mb-1`}
`;

const ConnectionInfo = styled.div`
    ${tw`flex items-center gap-2 text-xs font-mono text-gray-400 bg-black/30 px-3 py-1.5 rounded-lg border border-white/5 w-max`}
`;

const StatusBadge = styled.div<{ $status: string }>`
    ${tw`flex items-center gap-2 px-3 py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider shadow-lg`}
    
    background-color: ${({ $status }) => 
        $status === 'running' ? 'rgba(34, 197, 94, 0.1)' : 
        $status === 'offline' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(234, 179, 8, 0.1)'};
    
    color: ${({ $status }) => 
        $status === 'running' ? '#4ade80' : 
        $status === 'offline' ? '#f87171' : '#facc15'};
    
    border: 1px solid ${({ $status }) => 
        $status === 'running' ? 'rgba(34, 197, 94, 0.2)' : 
        $status === 'offline' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(234, 179, 8, 0.2)'};

    .dot {
        ${tw`w-2 h-2 rounded-full`}
        background-color: currentColor;
        box-shadow: 0 0 8px currentColor;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;

const StatsGrid = styled.div`
    ${tw`grid grid-cols-3 divide-x divide-white/5 p-6`}
`;

const StatBox = styled.div`
    ${tw`flex flex-col items-center justify-center px-2 text-center`}
`;

const StatValue = styled.div`
    ${tw`text-lg font-bold text-white mb-1`}
`;

const StatLabel = styled.div`
    ${tw`text-[10px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-1.5 mb-2`}
`;

const ProgressBarContainer = styled.div`
    ${tw`w-full h-1.5 bg-gray-800 rounded-full overflow-hidden`}
`;

const ProgressBarFill = styled.div<{ $percent: number; $color: string }>`
    ${tw`h-full rounded-full transition-all duration-1000 ease-out`}
    width: ${props => props.$percent}%;
    background: ${props => props.$color};
    box-shadow: 0 0 10px ${props => props.$color};
`;

export default memo(({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<any>(null);
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch(() => {});

    useEffect(() => {
        getStats();
        interval.current = setInterval(getStats, 30000);
        return () => clearInterval(interval.current);
    }, []);

    const status = stats?.status || (server.status === 'installing' ? 'starting' : 'offline');
    const isRunning = status === 'running';

    // Limits
    const cpuLimit = server.limits.cpu;
    const memoryLimit = server.limits.memory;
    const diskLimit = server.limits.disk;

    return (
        <CardWrapper 
            to={`/server/${server.id}`} 
            className={className} 
            $status={status}
            animate={{
                boxShadow: status === 'starting' 
                    ? ['0 0 15px -2px rgba(96, 165, 250, 0.25)', '0 0 30px 2px rgba(96, 165, 250, 0.5)', '0 0 15px -2px rgba(96, 165, 250, 0.25)']
                    : undefined
            }}
            transition={{
                boxShadow: status === 'starting' ? { repeat: Infinity, duration: 2, ease: "easeInOut" } : undefined
            }}
        >
            <Header>
                <div>
                    <ServerName>{server.name}</ServerName>
                    <ConnectionInfo>
                        <FontAwesomeIcon icon={faNetworkWired} />
                        {server.allocations.find(a => a.isDefault)?.alias || ip(server.allocations.find(a => a.isDefault)?.ip || '')}
                    </ConnectionInfo>
                </div>
                <StatusBadge $status={status}>
                    <div className="dot" />
                    {status}
                </StatusBadge>
            </Header>

            {!stats && isRunning ? (
                <div css={tw`p-10 flex justify-center`}>
                    <Spinner size={'small'} />
                </div>
            ) : !isRunning ? (
                <div css={tw`p-10 text-center`}>
                    <FontAwesomeIcon icon={faSignal} css={tw`text-gray-700 text-3xl mb-3`} />
                    <div css={tw`text-xs font-mono text-gray-600`}>SERVER IS OFFLINE</div>
                </div>
            ) : (
                <StatsGrid>
                    {/* CPU */}
                    <StatBox>
                        <StatLabel><FontAwesomeIcon icon={faMicrochip} /> CPU</StatLabel>
                        <StatValue>{stats!.cpuUsagePercent.toFixed(1)}%</StatValue>
                        <ProgressBarContainer>
                            <ProgressBarFill 
                                $percent={Math.min(stats!.cpuUsagePercent, 100)} 
                                $color="#60a5fa" // Blue
                            />
                        </ProgressBarContainer>
                    </StatBox>

                    {/* RAM */}
                    <StatBox>
                        <StatLabel><FontAwesomeIcon icon={faMemory} /> MEM</StatLabel>
                        <StatValue>{bytesToString(stats!.memoryUsageInBytes)}</StatValue>
                        <ProgressBarContainer>
                            <ProgressBarFill 
                                $percent={(stats!.memoryUsageInBytes / (memoryLimit * 1024 * 1024)) * 100} 
                                $color="#c084fc" // Purple
                            />
                        </ProgressBarContainer>
                    </StatBox>

                    {/* DISK */}
                    <StatBox>
                        <StatLabel><FontAwesomeIcon icon={faHdd} /> DISK</StatLabel>
                        <StatValue>{bytesToString(stats!.diskUsageInBytes)}</StatValue>
                        <ProgressBarContainer>
                            <ProgressBarFill 
                                $percent={(stats!.diskUsageInBytes / (diskLimit * 1024 * 1024)) * 100} 
                                $color="#f472b6" // Pink
                            />
                        </ProgressBarContainer>
                    </StatBox>
                </StatsGrid>
            )}
        </CardWrapper>
    );
}, isEqual);