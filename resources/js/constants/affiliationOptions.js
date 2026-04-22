/**
 * Official affiliation lists for student college vs employee/office selection.
 * Must stay in sync with App\Models\User::allowedStudentColleges() and allowedFacultyOffices().
 */
export const STUDENT_COLLEGES = [
    'College of Computer Studies',
    'College of Arts and Sciences',
    'School of Business and Management',
    'College of Agriculture',
    'College of Nursing',
    'College of Engineering',
    'School of Education',
    'School of Law',
    'School of Medicine',
];

export const FACULTY_OFFICES = [
    'Office of the President',
    'Office of the Vice-President Higher Education',
    'Office of the Vice President',
    'Office of the Scholarship Guild',
    'Office of Student and Affairs',
    'SACDEV',
    "Treasurer's Office",
    'Finance',
    'Guidance Counselor',
    'Research Ethics Office',
    'Computer Studies Employee and Admin',
    'Nursing Employee and Admin',
    'Arts and Sciences Admin Office',
    'OMM',
    'OVPMM',
    'Agriculture Office',
    'PPO',
    'CISO Office',
    'School of Law Office',
    'School of Medicine Office',
    'Engineering Admin Office',
    'Sociology Department',
    'IDE Department',
];

/** @param {'college' | 'office_department'} affiliationType */
export function affiliationNamesForType(affiliationType) {
    return affiliationType === 'college' ? STUDENT_COLLEGES : FACULTY_OFFICES;
}
