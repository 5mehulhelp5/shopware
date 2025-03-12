/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */

// declare global {
//     interface Window {
//         loadDIVEUtil: {
//             isLoaded: boolean;
//             promise: Promise<void> | null;
//             promiseResolve: (value: void | PromiseLike<void>) => void;
//         };
//         DIVE: typeof import("@shopware-ag/dive").DIVE
//     }
// }

export async function loadDIVE(): Promise<void> {

    if (!window.loadDIVEUtil) {
        window.loadDIVEUtil = {
            isLoaded: false,
            promise: null,
            promiseResolve: () => {},
        };
    }

    /* eslint-disable */
    if (window.loadDIVEUtil.isLoaded) {
        return;
    }

    if (window.loadDIVEUtil.promise) {
        await window.loadDIVEUtil.promise;
        return;
    }

    window.loadDIVEUtil.promise = new Promise((resolve) => {
        window.loadDIVEUtil.promiseResolve = resolve;
    });

    if (!window.DIVE) {
        const imported = await import("@shopware-ag/dive");
        window.DIVE = imported.DIVE;
    }

    // if (!window.threeJsAddons) {
    //     window.threeJsAddons = {};
    // }

    // if (!window.threeJsAddons?.OrbitControls) {
    //     const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
    //     window.threeJsAddons.OrbitControls = OrbitControls;
    // }

    // if (!window.threeJsAddons?.USDZExporter) {
    //     const { USDZExporter } = await import('three/examples/jsm/exporters/USDZExporter.js');
    //     window.threeJsAddons.USDZExporter = USDZExporter;
    // }

    // if (!window.threeJsAddons?.XREstimatedLight) {
    //     const { XREstimatedLight } = await import('three/examples/jsm/webxr/XREstimatedLight.js');
    //     window.threeJsAddons.XREstimatedLight = XREstimatedLight;
    // }

    // if (!window.threeJsAddons?.GLTFLoader) {
    //     const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js');
    //     window.threeJsAddons.GLTFLoader = GLTFLoader;
    // }

    // if (!window.threeJsAddons?.DRACOLoader) {
    //     const { DRACOLoader } = await import('three/examples/jsm/loaders/DRACOLoader.js');
    //     window.threeJsAddons.DRACOLoader = DRACOLoader;
    // }

    // if (!window.threeJsAddons?.DRACOLibPath) {
    //     window.threeJsAddons.DRACOLibPath = 'three/examples/jsm/libs/draco/';
    // }

    // if (!window.threeJsAddons?.MathUtils) {
    //     window.threeJsAddons.MathUtils = await import('three/src/math/MathUtils.js');
    // }

    window.loadDIVEUtil.promiseResolve();
    window.loadDIVEUtil.isLoaded = true;
    /* eslint-enable */
}
