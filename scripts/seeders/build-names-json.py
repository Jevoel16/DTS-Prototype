import csv
import json
import re
import unicodedata


def strip_accents(text):
    # Handle pre-composed Spanish ñ/Ñ and UTF-8 mis-decoded byte sequences (Ã±) from ISO-8859-1 CSV reading
    text = text.replace('Ã±', 'n').replace('Ã\x91', 'N').replace('ñ', 'n').replace('Ñ', 'N')
    nfd = unicodedata.normalize('NFD', text)
    return ''.join(c for c in nfd if unicodedata.category(c) != 'Mn')


def build_names_json():
    first_names = []
    last_names = []

    # Open CSV file with ISO-8859-1 encoding to handle special Spanish accents/characters from raw data.
    with open('pop_names.csv', mode='r', encoding='iso-8859-1') as f:
        reader = csv.reader(f)
        header = next(reader)
        rank = 0
        for row in reader:
            if not row:
                continue
            rank += 1

            # Extract forename and strip accents to guarantee 7-bit ASCII compatibility
            if len(row) >= 3 and row[1].strip():
                fname = strip_accents(row[1].strip())
                try:
                    fcount = int(row[2].replace(',', '').strip())
                except ValueError:
                    # Fallback to rank-based dynamic weighting if raw count string parsing fails
                    fcount = max(1, 100000 - rank * 90)
                first_names.append({'name': fname, 'weight': fcount})

            # Extract lastname and count, stripping rank numbers or notes attached to surname entries
            if len(row) >= 8 and row[7].strip():
                lraw = row[7].strip()
                m_count = re.search(r'\(([\d,]+)\)', lraw)
                if m_count:
                    lcount = int(m_count.group(1).replace(',', ''))
                else:
                    # Fallback weight when count is not directly present in parentheses
                    lcount = max(1000, 300000 - rank * 280)

                lname = re.sub(r'^\d+\.\s*', '', lraw)
                lname = re.sub(r'\s*\*\s*.*$', '', lname).strip()
                if lname:
                    lname_clean = strip_accents(lname)
                    last_names.append({'name': lname_clean, 'weight': lcount})

    data = {
        'first_names': first_names,
        'last_names': last_names
    }

    output_path = 'scripts/seeders/names_data.json'
    with open(output_path, 'w', encoding='utf-8') as out:
        json.dump(data, out, ensure_ascii=False, indent=2)

    print(f"Successfully generated {output_path} with {len(first_names)} forenames and {len(last_names)} lastnames.")


if __name__ == '__main__':
    build_names_json()
