# Content Import Strategy for Empty Subjects

## Target Subjects
1. **Technical Drawing**
2. **Financial Accounting**

---

## Available Sources Found

### Technical Drawing
- ✅ WAEC official e-learning platform (waeconline.org.ng)
- ✅ MyTopSchools.com (2008-2022 past questions PDF)
- ✅ MySchoolGist.com (comprehensive past questions collection)
- ✅ WAEC past questions from 1999-2021 available
- ✅ Official WAEC syllabus available

### Financial Accounting  
- ✅ MySchoolGist.com (comprehensive WAEC past questions)
- ✅ CampusCybercafe.com (NECO past questions with answers)
- ✅ TheStudentsFellow.com (WASSCE past questions 2016-2024)
- ✅ StudentVillage.com (JAMB/WAEC/NECO combined)
- ✅ MyTopSchools.com (comprehensive PDF collection 1995-2014+)
- ✅ Official WAEC syllabus available

---

## Import Strategy

### Phase 1: Topic Structure Creation

#### Technical Drawing Topics (Based on WAEC Syllabus)
1. **Drawing Materials and Equipment**
   - Drawing instruments, papers, scales
   - Care and maintenance of equipment
2. **Lines, Lettersing and Dimensioning**
   - Types of lines and their uses
   - Lettering techniques
   - Dimensioning rules and practices
3. **Geometric Constructions**
   - Basic geometric shapes
   - Angle constructions
   - Circle constructions
4. **Projections**
   - Orthographic projections
   - First and third angle projections
   - Auxiliary views
5. **Sections and Developments**
   - Sectional views
   - Development of surfaces
   - Intersection of solids
6. **Building Drawing**
   - Building plans and elevations
   - Detailed drawings
   - Architectural symbols
7. **Machine Drawing**
   - Assembly drawings
   - Detail drawings
   - Mechanical components

#### Financial Accounting Topics (Based on WAEC Syllabus)
1. **Introduction to Bookkeeping**
   - Definition and importance
   - Accounting equation
   - Double entry principle
2. **Ledger Accounts**
   - Personal accounts
   - Real accounts
   - Nominal accounts
3. **Cash Books**
   - Petty cash book
   - Three-column cash book
   - Analytical petty cash book
4. **Bank Reconciliation**
   - Bank statements
   - Reconciliation procedures
   - Unpresented cheques
5. **Trial Balance**
   - Preparation
   - Errors and corrections
6. **Final Accounts**
   - Trading account
   - Profit and loss account
   - Balance sheet
7. **Control Accounts**
   - Sales ledger control
   - Purchases ledger control
8. **Depreciation**
   - Methods of depreciation
   - Accounting treatment
9. **Partnership Accounts**
   - Partnership agreements
   - Profit sharing
   - Admission and retirement
10. **Company Accounts**
    - Share capital
    - Reserves and provisions
    - Final accounts of companies

---

### Phase 2: Database Schema Updates

#### Create Subject Year Entries
```sql
-- Technical Drawing
INSERT INTO subjectyear (subjectnamrid, year, category) VALUES
((SELECT id FROM subjectname WHERE name = 'Technical Drawing'), '2024', 'utme'),
((SELECT id FROM subjectname WHERE name = 'Technical Drawing'), '2024', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Technical Drawing'), '2023', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Technical Drawing'), '2022', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Technical Drawing'), '2021', 'ssce');

-- Financial Accounting  
INSERT INTO subjectyear (subjectnamrid, year, category) VALUES
((SELECT id FROM subjectname WHERE name = 'Financial accounting'), '2024', 'utme'),
((SELECT id FROM subjectname WHERE name = 'Financial accounting'), '2024', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Financial accounting'), '2023', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Financial accounting'), '2022', 'ssce'),
((SELECT id FROM subjectname WHERE name = 'Financial accounting'), '2021', 'ssce');
```

#### Create Topic Mapping Tables
```sql
-- Technical Drawing Mapping Table
CREATE TABLE IF NOT EXISTS `mapping_technical_drawing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Financial Accounting Mapping Table
CREATE TABLE IF NOT EXISTS `mapping_financial_accounting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

### Phase 3: Content Acquisition Options

#### Option A: Manual Download and Import
1. Download PDF past questions from identified sources
2. Convert PDF content to digital format
3. Manually categorize questions by topics
4. Import into database using structured format

**Pros:** 
- Full control over content quality
- Can ensure proper formatting
- Can verify answer accuracy

**Cons:**
- Time-intensive (manual process)
- Requires PDF conversion tools
- Subject to copyright considerations

#### Option B: Semi-Automated Import
1. Download available digital content
2. Use text extraction tools
3. Apply automated topic classification
4. Manual review and correction

**Pros:**
- Faster than fully manual
- Still maintains quality control
- Can leverage existing digital content

**Cons:**
- Requires some technical setup
- May need manual correction
- Classification accuracy varies

#### Option C: User Contribution System
1. Create content submission interface
2. Allow teachers/students to contribute questions
3. Implement review and approval workflow
4. Gradually build question database

**Pros:**
- Community-driven content growth
- Minimal initial effort
- Sustainable long-term solution

**Cons:**
- Slow initial content build
- Requires quality control system
- Dependent on user participation

---

### Phase 4: Recommended Implementation Approach

#### **Hybrid Approach:**
1. **Start with Option B (Semi-Automated)** for immediate content
2. **Implement Option C (User Contribution)** for long-term growth
3. **Use Option A (Manual)** for quality control of critical content

#### **Timeline:**
- **Week 1:** Download and process available PDF content
- **Week 2:** Implement automated classification system
- **Week 3:** Manual review and correction
- **Week 4:** Launch user contribution system
- **Ongoing:** Quality control and content expansion

---

### Phase 5: Content Quality Standards

#### Question Format Requirements:
- Clear, unambiguous question text
- 4 options (A, B, C, D) for multiple choice
- Single correct answer indicated
- Topic classification accuracy > 90%
- Year and exam type properly tagged

#### Metadata Requirements:
- Difficulty level (easy/medium/hard)
- Topic and subtopic classification
- Keywords for search optimization
- Explanation notes for wrong answers
- Historical performance data if available

---

## Next Steps

1. **Confirm GitHub repository URL** for version control setup
2. **Choose content acquisition approach** (A, B, or C)
3. **Download sample content** from identified sources
4. **Create database schema updates** for new subjects
5. **Begin content import process**

---

*Strategy document created for Technical Drawing and Financial Accounting content population*