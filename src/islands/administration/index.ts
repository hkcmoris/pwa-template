import { initAdminTransfer } from './admin-transfer';
import { initAdminLogo } from './admin-logo';

export default (root: HTMLElement) => {
    initAdminTransfer(root);
    initAdminLogo(root);
};
