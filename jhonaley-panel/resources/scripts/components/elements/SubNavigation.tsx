import styled from 'styled-components/macro';
import tw, { theme } from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full border-b border-white/5 overflow-x-auto hidden md:block`}; 
    
    /* FIX: Ganti bg-[#09090b]/50 jadi RGBA manual */
    background-color: rgba(9, 9, 11, 0.5);

    & > div {
        ${tw`flex items-center text-sm mx-auto px-4 md:px-8`};
        max-width: 1400px;

        & > a,
        & > div {
            ${tw`inline-block py-4 px-2 mr-4 text-gray-400 no-underline whitespace-nowrap transition-all duration-200 border-b-2 border-transparent`};

            &:hover {
                ${tw`text-gray-200`};
            }

            &:active,
            &.active {
                ${tw`text-indigo-400 border-indigo-500`};
                box-shadow: none;
            }
            
            svg {
                ${tw`mr-2`}
            }
        }
    }
`;

export default SubNavigation;