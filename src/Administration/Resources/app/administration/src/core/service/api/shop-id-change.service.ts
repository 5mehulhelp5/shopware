/**
 * @sw-package framework
 */
import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

type MatchingFingerprint = {
    identifier: string,
    storedStamp: string,
    score: number,
}

type MismatchingFingerprint = {
    identifier: string,
    storedStamp: string,
    expectedStamp: string,
    score: number,
}

type FingerprintComparisonResult = {
    matchingFingerprints: MatchingFingerprint[],
    mismatchingFingerprints: MismatchingFingerprint[],
    score: number,
    threshold: number,
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
/**
 * @private
 */
export default class ShopIdChangeService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'shopIdChangeService';
    }

    /**
     * @returns {Promise<Array<{key: string, description: string}>>}
     */
    getChangeStrategies() {
        return this.httpClient
            .get('app-system/shop-id/change-strategies', {
                headers: this.getBasicHeaders(),
            })
            .then(({ data }) => {
                return Object.entries(data).map(
                    ([
                        key,
                        description,
                    ]) => {
                        return { name: key, description };
                    },
                );
            });
    }

    /**
     * @param {{name: string}} strategy
     * @returns {*}
     */
    changeShopId({ name }) {
        return this.httpClient.post(
            'app-system/shop-id/change',
            { strategy: name },
            {
                headers: this.getBasicHeaders(),
            },
        );
    }

    /**
     * @returns {Promise<FingerprintComparisonResult | null>}
     */
    getFingerprints() {
        return this.httpClient
            .get('app-system/shop-id/fingerprints', {
                headers: this.getBasicHeaders(),
            })
            .then((resp) => {
                if (resp.status === 204) {
                    return null;
                }
                return resp.data as FingerprintComparisonResult;
            });
    }
}

/**
 * @private
 */
export type { FingerprintComparisonResult };
