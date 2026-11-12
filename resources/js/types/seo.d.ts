export interface ISharedSeoProps {
    siteName: string;
    siteUrl: string;
    defaultDescription: string;
    locale: string;
    htmlLang: string;
    defaultOgImage: string | null;
    twitterSite: string | null;
    twitterCreator: string | null;
    googleSiteVerification: string | null;
    bingSiteVerification: string | null;
    yandexVerification: string | null;
}
