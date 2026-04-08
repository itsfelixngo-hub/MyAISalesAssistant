export enum Role {
    Administrator = 100, //The highest level of permission. Admins have the power to access almost everything.
    Editor = 10, // Has access to all posts, pages, comments, categories, and tags, and can upload media.
    Author = 9, // Can write, upload media, edit, and publish their own posts.
    Contributor = 8, // Has no publishing or uploading capability but can write and edit their own posts until they are published.
    Viewer = 7, // Viewers can read and comment on posts and pages on private sites.
    Subscriber = 6, // People who subscribe to your site’s updates.
    Member = 1, // Member forum
}

export const ADMIN_Role = {
    [Role.Administrator]: 'SUPER_ADMIN',
    [Role.Editor]: 'EDITOR',
    [Role.Author]: 'AUTHOR',
    [Role.Contributor]: 'CONTRIBUTOR',
    [Role.Viewer]: 'VIEWER',
    [Role.Subscriber]: 'SUBSCRIBER',
    [Role.Member]: 'MEMBER',
} as const

export const ADMIN_Role_LABEL = {
    [Role.Administrator]: 'SUPER_ADMIN',
    [Role.Editor]: 'EDITOR',
    [Role.Author]: 'AUTHOR',
    [Role.Contributor]: 'CONTRIBUTOR',
    [Role.Viewer]: 'VIEWER',
    [Role.Subscriber]: 'SUBSCRIBER',
    [Role.Member]: 'MEMBER',
} as const

