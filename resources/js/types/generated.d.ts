declare namespace App {
namespace Data {
export type AuthUserData = {
id: number,
name: string,
email: string,
jabatan: string | null,
tingkat_akses: number | null,
unit: string | null,
is_superadmin: boolean,
initials: string,
};
}
namespace Enums {
export type DocumentEditScope = 'owner_only' | 'match_visibility';
export type DocumentStatus = 'berlaku' | 'kadaluarsa';
export type ExtractionStatus = 'not_applicable' | 'pending' | 'completed' | 'failed';
}
}
