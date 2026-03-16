import { initAdminTransfer } from './admin-transfer';
import { initAdminLogo } from './admin-logo';
import { initAdminAddress } from './admin-address';

export default (root: HTMLElement) => {
    initAdminTransfer(root);
    initAdminLogo(root);
    initAdminAddress(root);
};
