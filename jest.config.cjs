/** @type {import('jest').Config} */
module.exports = {
    preset: 'ts-jest',
    testEnvironment: 'jsdom',
    testMatch: ['<rootDir>/tests/frontend/**/*.(test|spec).ts?(x)'],
    // roots: ['<rootDir>/src'],
    moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json', 'node'],
    transform: {
        '^.+\\.(ts|tsx)$': ['ts-jest', { tsconfig: 'tsconfig.json' }],
    },
    // moduleNameMapper: {
    //     '\\.(css|scss|sass|less)$': '<rootDir>/src/test/styleMock.ts',
    // },
    moduleNameMapper: {
        '^@src/(.*)$': '<rootDir>/src/$1',
        '\\.(css|scss|sass|less)$': '<rootDir>/tests/frontend/styleMock.ts',
        // mock static assets if you import images/fonts in TS
        '\\.(png|jpe?g|gif|webp|avif|svg|woff2?)$':
            '<rootDir>/tests/frontend/fileMock.js',
    },
    collectCoverageFrom: ['src/**/*.{ts,tsx,js,jsx}', '!src/**/*.d.ts'],
};
