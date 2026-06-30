import * as React from 'react';
import tw, { TwStyle } from 'twin.macro';
import styled from 'styled-components/macro';

export type FlashMessageType = 'success' | 'info' | 'warning' | 'error';

interface Props {
    title?: string;
    children: string;
    type?: FlashMessageType;
}

const styling = (type?: FlashMessageType): TwStyle | string => {
    switch (type) {
        case 'error':
            return tw`bg-red-900/20 border-red-500 text-red-200`;
        case 'info':
            return tw`bg-purple-900/20 border-purple-500 text-purple-200`;
        case 'success':
            return tw`bg-green-900/20 border-green-500 text-green-200`;
        case 'warning':
            return tw`bg-yellow-900/20 border-yellow-500 text-yellow-200`;
        default:
            return '';
    }
};

const getBackground = (type?: FlashMessageType): TwStyle | string => {
    switch (type) {
        case 'error':
            return tw`bg-red-600`;
        case 'info':
            return tw`bg-purple-600`;
        case 'success':
            return tw`bg-green-600`;
        case 'warning':
            return tw`bg-yellow-600`;
        default:
            return '';
    }
};

const Container = styled.div<{ $type?: FlashMessageType }>`
    ${tw`p-3 border-l-4 rounded-r shadow-md items-center leading-normal flex w-full text-sm backdrop-blur-sm`};
    ${(props) => styling(props.$type)};
`;
Container.displayName = 'MessageBox.Container';

const MessageBox = ({ title, children, type }: Props) => (
    <Container css={tw`lg:inline-flex`} $type={type} role={'alert'}>
        {title && (
            <span
                className={'title'}
                css={[
                    tw`flex rounded uppercase px-2 py-1 text-xs font-bold mr-3 leading-none text-white shadow-sm`,
                    getBackground(type),
                ]}
            >
                {title}
            </span>
        )}
        <span css={tw`mr-2 text-left flex-auto font-medium`}>{children}</span>
    </Container>
);
MessageBox.displayName = 'MessageBox';

export default MessageBox;