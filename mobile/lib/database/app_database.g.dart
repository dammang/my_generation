// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $CachedPeopleTable extends CachedPeople
    with TableInfo<$CachedPeopleTable, CachedPerson> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedPeopleTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _ulidMeta = const VerificationMeta('ulid');
  @override
  late final GeneratedColumn<String> ulid = GeneratedColumn<String>(
    'ulid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _displayNameMeta = const VerificationMeta(
    'displayName',
  );
  @override
  late final GeneratedColumn<String> displayName = GeneratedColumn<String>(
    'display_name',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _nativeNameMeta = const VerificationMeta(
    'nativeName',
  );
  @override
  late final GeneratedColumn<String> nativeName = GeneratedColumn<String>(
    'native_name',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _genderMeta = const VerificationMeta('gender');
  @override
  late final GeneratedColumn<String> gender = GeneratedColumn<String>(
    'gender',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('unknown'),
  );
  static const VerificationMeta _birthDisplayMeta = const VerificationMeta(
    'birthDisplay',
  );
  @override
  late final GeneratedColumn<String> birthDisplay = GeneratedColumn<String>(
    'birth_display',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _birthYearMeta = const VerificationMeta(
    'birthYear',
  );
  @override
  late final GeneratedColumn<int> birthYear = GeneratedColumn<int>(
    'birth_year',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _deathDisplayMeta = const VerificationMeta(
    'deathDisplay',
  );
  @override
  late final GeneratedColumn<String> deathDisplay = GeneratedColumn<String>(
    'death_display',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _deathYearMeta = const VerificationMeta(
    'deathYear',
  );
  @override
  late final GeneratedColumn<int> deathYear = GeneratedColumn<int>(
    'death_year',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _isLivingMeta = const VerificationMeta(
    'isLiving',
  );
  @override
  late final GeneratedColumn<bool> isLiving = GeneratedColumn<bool>(
    'is_living',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("is_living" IN (0, 1))',
    ),
    defaultValue: const Constant(true),
  );
  static const VerificationMeta _redactedMeta = const VerificationMeta(
    'redacted',
  );
  @override
  late final GeneratedColumn<bool> redacted = GeneratedColumn<bool>(
    'redacted',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("redacted" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _verificationStatusMeta =
      const VerificationMeta('verificationStatus');
  @override
  late final GeneratedColumn<String> verificationStatus =
      GeneratedColumn<String>(
        'verification_status',
        aliasedName,
        true,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _photoUrlMeta = const VerificationMeta(
    'photoUrl',
  );
  @override
  late final GeneratedColumn<String> photoUrl = GeneratedColumn<String>(
    'photo_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _generationLabelMeta = const VerificationMeta(
    'generationLabel',
  );
  @override
  late final GeneratedColumn<String> generationLabel = GeneratedColumn<String>(
    'generation_label',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _cachedAtMeta = const VerificationMeta(
    'cachedAt',
  );
  @override
  late final GeneratedColumn<DateTime> cachedAt = GeneratedColumn<DateTime>(
    'cached_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    ulid,
    displayName,
    nativeName,
    gender,
    birthDisplay,
    birthYear,
    deathDisplay,
    deathYear,
    isLiving,
    redacted,
    verificationStatus,
    photoUrl,
    generationLabel,
    cachedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_people';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedPerson> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('ulid')) {
      context.handle(
        _ulidMeta,
        ulid.isAcceptableOrUnknown(data['ulid']!, _ulidMeta),
      );
    } else if (isInserting) {
      context.missing(_ulidMeta);
    }
    if (data.containsKey('display_name')) {
      context.handle(
        _displayNameMeta,
        displayName.isAcceptableOrUnknown(
          data['display_name']!,
          _displayNameMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_displayNameMeta);
    }
    if (data.containsKey('native_name')) {
      context.handle(
        _nativeNameMeta,
        nativeName.isAcceptableOrUnknown(data['native_name']!, _nativeNameMeta),
      );
    }
    if (data.containsKey('gender')) {
      context.handle(
        _genderMeta,
        gender.isAcceptableOrUnknown(data['gender']!, _genderMeta),
      );
    }
    if (data.containsKey('birth_display')) {
      context.handle(
        _birthDisplayMeta,
        birthDisplay.isAcceptableOrUnknown(
          data['birth_display']!,
          _birthDisplayMeta,
        ),
      );
    }
    if (data.containsKey('birth_year')) {
      context.handle(
        _birthYearMeta,
        birthYear.isAcceptableOrUnknown(data['birth_year']!, _birthYearMeta),
      );
    }
    if (data.containsKey('death_display')) {
      context.handle(
        _deathDisplayMeta,
        deathDisplay.isAcceptableOrUnknown(
          data['death_display']!,
          _deathDisplayMeta,
        ),
      );
    }
    if (data.containsKey('death_year')) {
      context.handle(
        _deathYearMeta,
        deathYear.isAcceptableOrUnknown(data['death_year']!, _deathYearMeta),
      );
    }
    if (data.containsKey('is_living')) {
      context.handle(
        _isLivingMeta,
        isLiving.isAcceptableOrUnknown(data['is_living']!, _isLivingMeta),
      );
    }
    if (data.containsKey('redacted')) {
      context.handle(
        _redactedMeta,
        redacted.isAcceptableOrUnknown(data['redacted']!, _redactedMeta),
      );
    }
    if (data.containsKey('verification_status')) {
      context.handle(
        _verificationStatusMeta,
        verificationStatus.isAcceptableOrUnknown(
          data['verification_status']!,
          _verificationStatusMeta,
        ),
      );
    }
    if (data.containsKey('photo_url')) {
      context.handle(
        _photoUrlMeta,
        photoUrl.isAcceptableOrUnknown(data['photo_url']!, _photoUrlMeta),
      );
    }
    if (data.containsKey('generation_label')) {
      context.handle(
        _generationLabelMeta,
        generationLabel.isAcceptableOrUnknown(
          data['generation_label']!,
          _generationLabelMeta,
        ),
      );
    }
    if (data.containsKey('cached_at')) {
      context.handle(
        _cachedAtMeta,
        cachedAt.isAcceptableOrUnknown(data['cached_at']!, _cachedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_cachedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {ulid};
  @override
  CachedPerson map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedPerson(
      ulid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}ulid'],
      )!,
      displayName: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}display_name'],
      )!,
      nativeName: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}native_name'],
      ),
      gender: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}gender'],
      )!,
      birthDisplay: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}birth_display'],
      ),
      birthYear: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}birth_year'],
      ),
      deathDisplay: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}death_display'],
      ),
      deathYear: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}death_year'],
      ),
      isLiving: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}is_living'],
      )!,
      redacted: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}redacted'],
      )!,
      verificationStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}verification_status'],
      ),
      photoUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}photo_url'],
      ),
      generationLabel: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}generation_label'],
      ),
      cachedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}cached_at'],
      )!,
    );
  }

  @override
  $CachedPeopleTable createAlias(String alias) {
    return $CachedPeopleTable(attachedDatabase, alias);
  }
}

class CachedPerson extends DataClass implements Insertable<CachedPerson> {
  final String ulid;
  final String displayName;
  final String? nativeName;
  final String gender;
  final String? birthDisplay;
  final int? birthYear;
  final String? deathDisplay;
  final int? deathYear;
  final bool isLiving;

  /// Carried through from the server so the UI can show the same indicator
  /// offline as online. The device never decides this for itself.
  final bool redacted;
  final String? verificationStatus;
  final String? photoUrl;
  final String? generationLabel;
  final DateTime cachedAt;
  const CachedPerson({
    required this.ulid,
    required this.displayName,
    this.nativeName,
    required this.gender,
    this.birthDisplay,
    this.birthYear,
    this.deathDisplay,
    this.deathYear,
    required this.isLiving,
    required this.redacted,
    this.verificationStatus,
    this.photoUrl,
    this.generationLabel,
    required this.cachedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['ulid'] = Variable<String>(ulid);
    map['display_name'] = Variable<String>(displayName);
    if (!nullToAbsent || nativeName != null) {
      map['native_name'] = Variable<String>(nativeName);
    }
    map['gender'] = Variable<String>(gender);
    if (!nullToAbsent || birthDisplay != null) {
      map['birth_display'] = Variable<String>(birthDisplay);
    }
    if (!nullToAbsent || birthYear != null) {
      map['birth_year'] = Variable<int>(birthYear);
    }
    if (!nullToAbsent || deathDisplay != null) {
      map['death_display'] = Variable<String>(deathDisplay);
    }
    if (!nullToAbsent || deathYear != null) {
      map['death_year'] = Variable<int>(deathYear);
    }
    map['is_living'] = Variable<bool>(isLiving);
    map['redacted'] = Variable<bool>(redacted);
    if (!nullToAbsent || verificationStatus != null) {
      map['verification_status'] = Variable<String>(verificationStatus);
    }
    if (!nullToAbsent || photoUrl != null) {
      map['photo_url'] = Variable<String>(photoUrl);
    }
    if (!nullToAbsent || generationLabel != null) {
      map['generation_label'] = Variable<String>(generationLabel);
    }
    map['cached_at'] = Variable<DateTime>(cachedAt);
    return map;
  }

  CachedPeopleCompanion toCompanion(bool nullToAbsent) {
    return CachedPeopleCompanion(
      ulid: Value(ulid),
      displayName: Value(displayName),
      nativeName: nativeName == null && nullToAbsent
          ? const Value.absent()
          : Value(nativeName),
      gender: Value(gender),
      birthDisplay: birthDisplay == null && nullToAbsent
          ? const Value.absent()
          : Value(birthDisplay),
      birthYear: birthYear == null && nullToAbsent
          ? const Value.absent()
          : Value(birthYear),
      deathDisplay: deathDisplay == null && nullToAbsent
          ? const Value.absent()
          : Value(deathDisplay),
      deathYear: deathYear == null && nullToAbsent
          ? const Value.absent()
          : Value(deathYear),
      isLiving: Value(isLiving),
      redacted: Value(redacted),
      verificationStatus: verificationStatus == null && nullToAbsent
          ? const Value.absent()
          : Value(verificationStatus),
      photoUrl: photoUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(photoUrl),
      generationLabel: generationLabel == null && nullToAbsent
          ? const Value.absent()
          : Value(generationLabel),
      cachedAt: Value(cachedAt),
    );
  }

  factory CachedPerson.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedPerson(
      ulid: serializer.fromJson<String>(json['ulid']),
      displayName: serializer.fromJson<String>(json['displayName']),
      nativeName: serializer.fromJson<String?>(json['nativeName']),
      gender: serializer.fromJson<String>(json['gender']),
      birthDisplay: serializer.fromJson<String?>(json['birthDisplay']),
      birthYear: serializer.fromJson<int?>(json['birthYear']),
      deathDisplay: serializer.fromJson<String?>(json['deathDisplay']),
      deathYear: serializer.fromJson<int?>(json['deathYear']),
      isLiving: serializer.fromJson<bool>(json['isLiving']),
      redacted: serializer.fromJson<bool>(json['redacted']),
      verificationStatus: serializer.fromJson<String?>(
        json['verificationStatus'],
      ),
      photoUrl: serializer.fromJson<String?>(json['photoUrl']),
      generationLabel: serializer.fromJson<String?>(json['generationLabel']),
      cachedAt: serializer.fromJson<DateTime>(json['cachedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'ulid': serializer.toJson<String>(ulid),
      'displayName': serializer.toJson<String>(displayName),
      'nativeName': serializer.toJson<String?>(nativeName),
      'gender': serializer.toJson<String>(gender),
      'birthDisplay': serializer.toJson<String?>(birthDisplay),
      'birthYear': serializer.toJson<int?>(birthYear),
      'deathDisplay': serializer.toJson<String?>(deathDisplay),
      'deathYear': serializer.toJson<int?>(deathYear),
      'isLiving': serializer.toJson<bool>(isLiving),
      'redacted': serializer.toJson<bool>(redacted),
      'verificationStatus': serializer.toJson<String?>(verificationStatus),
      'photoUrl': serializer.toJson<String?>(photoUrl),
      'generationLabel': serializer.toJson<String?>(generationLabel),
      'cachedAt': serializer.toJson<DateTime>(cachedAt),
    };
  }

  CachedPerson copyWith({
    String? ulid,
    String? displayName,
    Value<String?> nativeName = const Value.absent(),
    String? gender,
    Value<String?> birthDisplay = const Value.absent(),
    Value<int?> birthYear = const Value.absent(),
    Value<String?> deathDisplay = const Value.absent(),
    Value<int?> deathYear = const Value.absent(),
    bool? isLiving,
    bool? redacted,
    Value<String?> verificationStatus = const Value.absent(),
    Value<String?> photoUrl = const Value.absent(),
    Value<String?> generationLabel = const Value.absent(),
    DateTime? cachedAt,
  }) => CachedPerson(
    ulid: ulid ?? this.ulid,
    displayName: displayName ?? this.displayName,
    nativeName: nativeName.present ? nativeName.value : this.nativeName,
    gender: gender ?? this.gender,
    birthDisplay: birthDisplay.present ? birthDisplay.value : this.birthDisplay,
    birthYear: birthYear.present ? birthYear.value : this.birthYear,
    deathDisplay: deathDisplay.present ? deathDisplay.value : this.deathDisplay,
    deathYear: deathYear.present ? deathYear.value : this.deathYear,
    isLiving: isLiving ?? this.isLiving,
    redacted: redacted ?? this.redacted,
    verificationStatus: verificationStatus.present
        ? verificationStatus.value
        : this.verificationStatus,
    photoUrl: photoUrl.present ? photoUrl.value : this.photoUrl,
    generationLabel: generationLabel.present
        ? generationLabel.value
        : this.generationLabel,
    cachedAt: cachedAt ?? this.cachedAt,
  );
  CachedPerson copyWithCompanion(CachedPeopleCompanion data) {
    return CachedPerson(
      ulid: data.ulid.present ? data.ulid.value : this.ulid,
      displayName: data.displayName.present
          ? data.displayName.value
          : this.displayName,
      nativeName: data.nativeName.present
          ? data.nativeName.value
          : this.nativeName,
      gender: data.gender.present ? data.gender.value : this.gender,
      birthDisplay: data.birthDisplay.present
          ? data.birthDisplay.value
          : this.birthDisplay,
      birthYear: data.birthYear.present ? data.birthYear.value : this.birthYear,
      deathDisplay: data.deathDisplay.present
          ? data.deathDisplay.value
          : this.deathDisplay,
      deathYear: data.deathYear.present ? data.deathYear.value : this.deathYear,
      isLiving: data.isLiving.present ? data.isLiving.value : this.isLiving,
      redacted: data.redacted.present ? data.redacted.value : this.redacted,
      verificationStatus: data.verificationStatus.present
          ? data.verificationStatus.value
          : this.verificationStatus,
      photoUrl: data.photoUrl.present ? data.photoUrl.value : this.photoUrl,
      generationLabel: data.generationLabel.present
          ? data.generationLabel.value
          : this.generationLabel,
      cachedAt: data.cachedAt.present ? data.cachedAt.value : this.cachedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedPerson(')
          ..write('ulid: $ulid, ')
          ..write('displayName: $displayName, ')
          ..write('nativeName: $nativeName, ')
          ..write('gender: $gender, ')
          ..write('birthDisplay: $birthDisplay, ')
          ..write('birthYear: $birthYear, ')
          ..write('deathDisplay: $deathDisplay, ')
          ..write('deathYear: $deathYear, ')
          ..write('isLiving: $isLiving, ')
          ..write('redacted: $redacted, ')
          ..write('verificationStatus: $verificationStatus, ')
          ..write('photoUrl: $photoUrl, ')
          ..write('generationLabel: $generationLabel, ')
          ..write('cachedAt: $cachedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    ulid,
    displayName,
    nativeName,
    gender,
    birthDisplay,
    birthYear,
    deathDisplay,
    deathYear,
    isLiving,
    redacted,
    verificationStatus,
    photoUrl,
    generationLabel,
    cachedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedPerson &&
          other.ulid == this.ulid &&
          other.displayName == this.displayName &&
          other.nativeName == this.nativeName &&
          other.gender == this.gender &&
          other.birthDisplay == this.birthDisplay &&
          other.birthYear == this.birthYear &&
          other.deathDisplay == this.deathDisplay &&
          other.deathYear == this.deathYear &&
          other.isLiving == this.isLiving &&
          other.redacted == this.redacted &&
          other.verificationStatus == this.verificationStatus &&
          other.photoUrl == this.photoUrl &&
          other.generationLabel == this.generationLabel &&
          other.cachedAt == this.cachedAt);
}

class CachedPeopleCompanion extends UpdateCompanion<CachedPerson> {
  final Value<String> ulid;
  final Value<String> displayName;
  final Value<String?> nativeName;
  final Value<String> gender;
  final Value<String?> birthDisplay;
  final Value<int?> birthYear;
  final Value<String?> deathDisplay;
  final Value<int?> deathYear;
  final Value<bool> isLiving;
  final Value<bool> redacted;
  final Value<String?> verificationStatus;
  final Value<String?> photoUrl;
  final Value<String?> generationLabel;
  final Value<DateTime> cachedAt;
  final Value<int> rowid;
  const CachedPeopleCompanion({
    this.ulid = const Value.absent(),
    this.displayName = const Value.absent(),
    this.nativeName = const Value.absent(),
    this.gender = const Value.absent(),
    this.birthDisplay = const Value.absent(),
    this.birthYear = const Value.absent(),
    this.deathDisplay = const Value.absent(),
    this.deathYear = const Value.absent(),
    this.isLiving = const Value.absent(),
    this.redacted = const Value.absent(),
    this.verificationStatus = const Value.absent(),
    this.photoUrl = const Value.absent(),
    this.generationLabel = const Value.absent(),
    this.cachedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedPeopleCompanion.insert({
    required String ulid,
    required String displayName,
    this.nativeName = const Value.absent(),
    this.gender = const Value.absent(),
    this.birthDisplay = const Value.absent(),
    this.birthYear = const Value.absent(),
    this.deathDisplay = const Value.absent(),
    this.deathYear = const Value.absent(),
    this.isLiving = const Value.absent(),
    this.redacted = const Value.absent(),
    this.verificationStatus = const Value.absent(),
    this.photoUrl = const Value.absent(),
    this.generationLabel = const Value.absent(),
    required DateTime cachedAt,
    this.rowid = const Value.absent(),
  }) : ulid = Value(ulid),
       displayName = Value(displayName),
       cachedAt = Value(cachedAt);
  static Insertable<CachedPerson> custom({
    Expression<String>? ulid,
    Expression<String>? displayName,
    Expression<String>? nativeName,
    Expression<String>? gender,
    Expression<String>? birthDisplay,
    Expression<int>? birthYear,
    Expression<String>? deathDisplay,
    Expression<int>? deathYear,
    Expression<bool>? isLiving,
    Expression<bool>? redacted,
    Expression<String>? verificationStatus,
    Expression<String>? photoUrl,
    Expression<String>? generationLabel,
    Expression<DateTime>? cachedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (ulid != null) 'ulid': ulid,
      if (displayName != null) 'display_name': displayName,
      if (nativeName != null) 'native_name': nativeName,
      if (gender != null) 'gender': gender,
      if (birthDisplay != null) 'birth_display': birthDisplay,
      if (birthYear != null) 'birth_year': birthYear,
      if (deathDisplay != null) 'death_display': deathDisplay,
      if (deathYear != null) 'death_year': deathYear,
      if (isLiving != null) 'is_living': isLiving,
      if (redacted != null) 'redacted': redacted,
      if (verificationStatus != null) 'verification_status': verificationStatus,
      if (photoUrl != null) 'photo_url': photoUrl,
      if (generationLabel != null) 'generation_label': generationLabel,
      if (cachedAt != null) 'cached_at': cachedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedPeopleCompanion copyWith({
    Value<String>? ulid,
    Value<String>? displayName,
    Value<String?>? nativeName,
    Value<String>? gender,
    Value<String?>? birthDisplay,
    Value<int?>? birthYear,
    Value<String?>? deathDisplay,
    Value<int?>? deathYear,
    Value<bool>? isLiving,
    Value<bool>? redacted,
    Value<String?>? verificationStatus,
    Value<String?>? photoUrl,
    Value<String?>? generationLabel,
    Value<DateTime>? cachedAt,
    Value<int>? rowid,
  }) {
    return CachedPeopleCompanion(
      ulid: ulid ?? this.ulid,
      displayName: displayName ?? this.displayName,
      nativeName: nativeName ?? this.nativeName,
      gender: gender ?? this.gender,
      birthDisplay: birthDisplay ?? this.birthDisplay,
      birthYear: birthYear ?? this.birthYear,
      deathDisplay: deathDisplay ?? this.deathDisplay,
      deathYear: deathYear ?? this.deathYear,
      isLiving: isLiving ?? this.isLiving,
      redacted: redacted ?? this.redacted,
      verificationStatus: verificationStatus ?? this.verificationStatus,
      photoUrl: photoUrl ?? this.photoUrl,
      generationLabel: generationLabel ?? this.generationLabel,
      cachedAt: cachedAt ?? this.cachedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (ulid.present) {
      map['ulid'] = Variable<String>(ulid.value);
    }
    if (displayName.present) {
      map['display_name'] = Variable<String>(displayName.value);
    }
    if (nativeName.present) {
      map['native_name'] = Variable<String>(nativeName.value);
    }
    if (gender.present) {
      map['gender'] = Variable<String>(gender.value);
    }
    if (birthDisplay.present) {
      map['birth_display'] = Variable<String>(birthDisplay.value);
    }
    if (birthYear.present) {
      map['birth_year'] = Variable<int>(birthYear.value);
    }
    if (deathDisplay.present) {
      map['death_display'] = Variable<String>(deathDisplay.value);
    }
    if (deathYear.present) {
      map['death_year'] = Variable<int>(deathYear.value);
    }
    if (isLiving.present) {
      map['is_living'] = Variable<bool>(isLiving.value);
    }
    if (redacted.present) {
      map['redacted'] = Variable<bool>(redacted.value);
    }
    if (verificationStatus.present) {
      map['verification_status'] = Variable<String>(verificationStatus.value);
    }
    if (photoUrl.present) {
      map['photo_url'] = Variable<String>(photoUrl.value);
    }
    if (generationLabel.present) {
      map['generation_label'] = Variable<String>(generationLabel.value);
    }
    if (cachedAt.present) {
      map['cached_at'] = Variable<DateTime>(cachedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedPeopleCompanion(')
          ..write('ulid: $ulid, ')
          ..write('displayName: $displayName, ')
          ..write('nativeName: $nativeName, ')
          ..write('gender: $gender, ')
          ..write('birthDisplay: $birthDisplay, ')
          ..write('birthYear: $birthYear, ')
          ..write('deathDisplay: $deathDisplay, ')
          ..write('deathYear: $deathYear, ')
          ..write('isLiving: $isLiving, ')
          ..write('redacted: $redacted, ')
          ..write('verificationStatus: $verificationStatus, ')
          ..write('photoUrl: $photoUrl, ')
          ..write('generationLabel: $generationLabel, ')
          ..write('cachedAt: $cachedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedEdgesTable extends CachedEdges
    with TableInfo<$CachedEdgesTable, CachedEdge> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedEdgesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _parentUlidMeta = const VerificationMeta(
    'parentUlid',
  );
  @override
  late final GeneratedColumn<String> parentUlid = GeneratedColumn<String>(
    'parent_ulid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _childUlidMeta = const VerificationMeta(
    'childUlid',
  );
  @override
  late final GeneratedColumn<String> childUlid = GeneratedColumn<String>(
    'child_ulid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _kindMeta = const VerificationMeta('kind');
  @override
  late final GeneratedColumn<String> kind = GeneratedColumn<String>(
    'kind',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('biological'),
  );
  @override
  List<GeneratedColumn> get $columns => [parentUlid, childUlid, kind];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_edges';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedEdge> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('parent_ulid')) {
      context.handle(
        _parentUlidMeta,
        parentUlid.isAcceptableOrUnknown(data['parent_ulid']!, _parentUlidMeta),
      );
    } else if (isInserting) {
      context.missing(_parentUlidMeta);
    }
    if (data.containsKey('child_ulid')) {
      context.handle(
        _childUlidMeta,
        childUlid.isAcceptableOrUnknown(data['child_ulid']!, _childUlidMeta),
      );
    } else if (isInserting) {
      context.missing(_childUlidMeta);
    }
    if (data.containsKey('kind')) {
      context.handle(
        _kindMeta,
        kind.isAcceptableOrUnknown(data['kind']!, _kindMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {parentUlid, childUlid, kind};
  @override
  CachedEdge map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedEdge(
      parentUlid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}parent_ulid'],
      )!,
      childUlid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}child_ulid'],
      )!,
      kind: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}kind'],
      )!,
    );
  }

  @override
  $CachedEdgesTable createAlias(String alias) {
    return $CachedEdgesTable(attachedDatabase, alias);
  }
}

class CachedEdge extends DataClass implements Insertable<CachedEdge> {
  final String parentUlid;
  final String childUlid;
  final String kind;
  const CachedEdge({
    required this.parentUlid,
    required this.childUlid,
    required this.kind,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['parent_ulid'] = Variable<String>(parentUlid);
    map['child_ulid'] = Variable<String>(childUlid);
    map['kind'] = Variable<String>(kind);
    return map;
  }

  CachedEdgesCompanion toCompanion(bool nullToAbsent) {
    return CachedEdgesCompanion(
      parentUlid: Value(parentUlid),
      childUlid: Value(childUlid),
      kind: Value(kind),
    );
  }

  factory CachedEdge.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedEdge(
      parentUlid: serializer.fromJson<String>(json['parentUlid']),
      childUlid: serializer.fromJson<String>(json['childUlid']),
      kind: serializer.fromJson<String>(json['kind']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'parentUlid': serializer.toJson<String>(parentUlid),
      'childUlid': serializer.toJson<String>(childUlid),
      'kind': serializer.toJson<String>(kind),
    };
  }

  CachedEdge copyWith({String? parentUlid, String? childUlid, String? kind}) =>
      CachedEdge(
        parentUlid: parentUlid ?? this.parentUlid,
        childUlid: childUlid ?? this.childUlid,
        kind: kind ?? this.kind,
      );
  CachedEdge copyWithCompanion(CachedEdgesCompanion data) {
    return CachedEdge(
      parentUlid: data.parentUlid.present
          ? data.parentUlid.value
          : this.parentUlid,
      childUlid: data.childUlid.present ? data.childUlid.value : this.childUlid,
      kind: data.kind.present ? data.kind.value : this.kind,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedEdge(')
          ..write('parentUlid: $parentUlid, ')
          ..write('childUlid: $childUlid, ')
          ..write('kind: $kind')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(parentUlid, childUlid, kind);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedEdge &&
          other.parentUlid == this.parentUlid &&
          other.childUlid == this.childUlid &&
          other.kind == this.kind);
}

class CachedEdgesCompanion extends UpdateCompanion<CachedEdge> {
  final Value<String> parentUlid;
  final Value<String> childUlid;
  final Value<String> kind;
  final Value<int> rowid;
  const CachedEdgesCompanion({
    this.parentUlid = const Value.absent(),
    this.childUlid = const Value.absent(),
    this.kind = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedEdgesCompanion.insert({
    required String parentUlid,
    required String childUlid,
    this.kind = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : parentUlid = Value(parentUlid),
       childUlid = Value(childUlid);
  static Insertable<CachedEdge> custom({
    Expression<String>? parentUlid,
    Expression<String>? childUlid,
    Expression<String>? kind,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (parentUlid != null) 'parent_ulid': parentUlid,
      if (childUlid != null) 'child_ulid': childUlid,
      if (kind != null) 'kind': kind,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedEdgesCompanion copyWith({
    Value<String>? parentUlid,
    Value<String>? childUlid,
    Value<String>? kind,
    Value<int>? rowid,
  }) {
    return CachedEdgesCompanion(
      parentUlid: parentUlid ?? this.parentUlid,
      childUlid: childUlid ?? this.childUlid,
      kind: kind ?? this.kind,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (parentUlid.present) {
      map['parent_ulid'] = Variable<String>(parentUlid.value);
    }
    if (childUlid.present) {
      map['child_ulid'] = Variable<String>(childUlid.value);
    }
    if (kind.present) {
      map['kind'] = Variable<String>(kind.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedEdgesCompanion(')
          ..write('parentUlid: $parentUlid, ')
          ..write('childUlid: $childUlid, ')
          ..write('kind: $kind, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedUnionsTable extends CachedUnions
    with TableInfo<$CachedUnionsTable, CachedUnion> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedUnionsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _ulidMeta = const VerificationMeta('ulid');
  @override
  late final GeneratedColumn<String> ulid = GeneratedColumn<String>(
    'ulid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _partnerUlidsMeta = const VerificationMeta(
    'partnerUlids',
  );
  @override
  late final GeneratedColumn<String> partnerUlids = GeneratedColumn<String>(
    'partner_ulids',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _childUlidsMeta = const VerificationMeta(
    'childUlids',
  );
  @override
  late final GeneratedColumn<String> childUlids = GeneratedColumn<String>(
    'child_ulids',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _unionTypeMeta = const VerificationMeta(
    'unionType',
  );
  @override
  late final GeneratedColumn<String> unionType = GeneratedColumn<String>(
    'union_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('marriage'),
  );
  static const VerificationMeta _marriageYearMeta = const VerificationMeta(
    'marriageYear',
  );
  @override
  late final GeneratedColumn<int> marriageYear = GeneratedColumn<int>(
    'marriage_year',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _orderIndexMeta = const VerificationMeta(
    'orderIndex',
  );
  @override
  late final GeneratedColumn<int> orderIndex = GeneratedColumn<int>(
    'order_index',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(1),
  );
  @override
  List<GeneratedColumn> get $columns => [
    ulid,
    partnerUlids,
    childUlids,
    unionType,
    marriageYear,
    orderIndex,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_unions';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedUnion> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('ulid')) {
      context.handle(
        _ulidMeta,
        ulid.isAcceptableOrUnknown(data['ulid']!, _ulidMeta),
      );
    } else if (isInserting) {
      context.missing(_ulidMeta);
    }
    if (data.containsKey('partner_ulids')) {
      context.handle(
        _partnerUlidsMeta,
        partnerUlids.isAcceptableOrUnknown(
          data['partner_ulids']!,
          _partnerUlidsMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_partnerUlidsMeta);
    }
    if (data.containsKey('child_ulids')) {
      context.handle(
        _childUlidsMeta,
        childUlids.isAcceptableOrUnknown(data['child_ulids']!, _childUlidsMeta),
      );
    } else if (isInserting) {
      context.missing(_childUlidsMeta);
    }
    if (data.containsKey('union_type')) {
      context.handle(
        _unionTypeMeta,
        unionType.isAcceptableOrUnknown(data['union_type']!, _unionTypeMeta),
      );
    }
    if (data.containsKey('marriage_year')) {
      context.handle(
        _marriageYearMeta,
        marriageYear.isAcceptableOrUnknown(
          data['marriage_year']!,
          _marriageYearMeta,
        ),
      );
    }
    if (data.containsKey('order_index')) {
      context.handle(
        _orderIndexMeta,
        orderIndex.isAcceptableOrUnknown(data['order_index']!, _orderIndexMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {ulid};
  @override
  CachedUnion map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedUnion(
      ulid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}ulid'],
      )!,
      partnerUlids: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}partner_ulids'],
      )!,
      childUlids: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}child_ulids'],
      )!,
      unionType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}union_type'],
      )!,
      marriageYear: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}marriage_year'],
      ),
      orderIndex: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}order_index'],
      )!,
    );
  }

  @override
  $CachedUnionsTable createAlias(String alias) {
    return $CachedUnionsTable(attachedDatabase, alias);
  }
}

class CachedUnion extends DataClass implements Insertable<CachedUnion> {
  final String ulid;
  final String partnerUlids;
  final String childUlids;
  final String unionType;
  final int? marriageYear;
  final int orderIndex;
  const CachedUnion({
    required this.ulid,
    required this.partnerUlids,
    required this.childUlids,
    required this.unionType,
    this.marriageYear,
    required this.orderIndex,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['ulid'] = Variable<String>(ulid);
    map['partner_ulids'] = Variable<String>(partnerUlids);
    map['child_ulids'] = Variable<String>(childUlids);
    map['union_type'] = Variable<String>(unionType);
    if (!nullToAbsent || marriageYear != null) {
      map['marriage_year'] = Variable<int>(marriageYear);
    }
    map['order_index'] = Variable<int>(orderIndex);
    return map;
  }

  CachedUnionsCompanion toCompanion(bool nullToAbsent) {
    return CachedUnionsCompanion(
      ulid: Value(ulid),
      partnerUlids: Value(partnerUlids),
      childUlids: Value(childUlids),
      unionType: Value(unionType),
      marriageYear: marriageYear == null && nullToAbsent
          ? const Value.absent()
          : Value(marriageYear),
      orderIndex: Value(orderIndex),
    );
  }

  factory CachedUnion.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedUnion(
      ulid: serializer.fromJson<String>(json['ulid']),
      partnerUlids: serializer.fromJson<String>(json['partnerUlids']),
      childUlids: serializer.fromJson<String>(json['childUlids']),
      unionType: serializer.fromJson<String>(json['unionType']),
      marriageYear: serializer.fromJson<int?>(json['marriageYear']),
      orderIndex: serializer.fromJson<int>(json['orderIndex']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'ulid': serializer.toJson<String>(ulid),
      'partnerUlids': serializer.toJson<String>(partnerUlids),
      'childUlids': serializer.toJson<String>(childUlids),
      'unionType': serializer.toJson<String>(unionType),
      'marriageYear': serializer.toJson<int?>(marriageYear),
      'orderIndex': serializer.toJson<int>(orderIndex),
    };
  }

  CachedUnion copyWith({
    String? ulid,
    String? partnerUlids,
    String? childUlids,
    String? unionType,
    Value<int?> marriageYear = const Value.absent(),
    int? orderIndex,
  }) => CachedUnion(
    ulid: ulid ?? this.ulid,
    partnerUlids: partnerUlids ?? this.partnerUlids,
    childUlids: childUlids ?? this.childUlids,
    unionType: unionType ?? this.unionType,
    marriageYear: marriageYear.present ? marriageYear.value : this.marriageYear,
    orderIndex: orderIndex ?? this.orderIndex,
  );
  CachedUnion copyWithCompanion(CachedUnionsCompanion data) {
    return CachedUnion(
      ulid: data.ulid.present ? data.ulid.value : this.ulid,
      partnerUlids: data.partnerUlids.present
          ? data.partnerUlids.value
          : this.partnerUlids,
      childUlids: data.childUlids.present
          ? data.childUlids.value
          : this.childUlids,
      unionType: data.unionType.present ? data.unionType.value : this.unionType,
      marriageYear: data.marriageYear.present
          ? data.marriageYear.value
          : this.marriageYear,
      orderIndex: data.orderIndex.present
          ? data.orderIndex.value
          : this.orderIndex,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedUnion(')
          ..write('ulid: $ulid, ')
          ..write('partnerUlids: $partnerUlids, ')
          ..write('childUlids: $childUlids, ')
          ..write('unionType: $unionType, ')
          ..write('marriageYear: $marriageYear, ')
          ..write('orderIndex: $orderIndex')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    ulid,
    partnerUlids,
    childUlids,
    unionType,
    marriageYear,
    orderIndex,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedUnion &&
          other.ulid == this.ulid &&
          other.partnerUlids == this.partnerUlids &&
          other.childUlids == this.childUlids &&
          other.unionType == this.unionType &&
          other.marriageYear == this.marriageYear &&
          other.orderIndex == this.orderIndex);
}

class CachedUnionsCompanion extends UpdateCompanion<CachedUnion> {
  final Value<String> ulid;
  final Value<String> partnerUlids;
  final Value<String> childUlids;
  final Value<String> unionType;
  final Value<int?> marriageYear;
  final Value<int> orderIndex;
  final Value<int> rowid;
  const CachedUnionsCompanion({
    this.ulid = const Value.absent(),
    this.partnerUlids = const Value.absent(),
    this.childUlids = const Value.absent(),
    this.unionType = const Value.absent(),
    this.marriageYear = const Value.absent(),
    this.orderIndex = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedUnionsCompanion.insert({
    required String ulid,
    required String partnerUlids,
    required String childUlids,
    this.unionType = const Value.absent(),
    this.marriageYear = const Value.absent(),
    this.orderIndex = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : ulid = Value(ulid),
       partnerUlids = Value(partnerUlids),
       childUlids = Value(childUlids);
  static Insertable<CachedUnion> custom({
    Expression<String>? ulid,
    Expression<String>? partnerUlids,
    Expression<String>? childUlids,
    Expression<String>? unionType,
    Expression<int>? marriageYear,
    Expression<int>? orderIndex,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (ulid != null) 'ulid': ulid,
      if (partnerUlids != null) 'partner_ulids': partnerUlids,
      if (childUlids != null) 'child_ulids': childUlids,
      if (unionType != null) 'union_type': unionType,
      if (marriageYear != null) 'marriage_year': marriageYear,
      if (orderIndex != null) 'order_index': orderIndex,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedUnionsCompanion copyWith({
    Value<String>? ulid,
    Value<String>? partnerUlids,
    Value<String>? childUlids,
    Value<String>? unionType,
    Value<int?>? marriageYear,
    Value<int>? orderIndex,
    Value<int>? rowid,
  }) {
    return CachedUnionsCompanion(
      ulid: ulid ?? this.ulid,
      partnerUlids: partnerUlids ?? this.partnerUlids,
      childUlids: childUlids ?? this.childUlids,
      unionType: unionType ?? this.unionType,
      marriageYear: marriageYear ?? this.marriageYear,
      orderIndex: orderIndex ?? this.orderIndex,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (ulid.present) {
      map['ulid'] = Variable<String>(ulid.value);
    }
    if (partnerUlids.present) {
      map['partner_ulids'] = Variable<String>(partnerUlids.value);
    }
    if (childUlids.present) {
      map['child_ulids'] = Variable<String>(childUlids.value);
    }
    if (unionType.present) {
      map['union_type'] = Variable<String>(unionType.value);
    }
    if (marriageYear.present) {
      map['marriage_year'] = Variable<int>(marriageYear.value);
    }
    if (orderIndex.present) {
      map['order_index'] = Variable<int>(orderIndex.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedUnionsCompanion(')
          ..write('ulid: $ulid, ')
          ..write('partnerUlids: $partnerUlids, ')
          ..write('childUlids: $childUlids, ')
          ..write('unionType: $unionType, ')
          ..write('marriageYear: $marriageYear, ')
          ..write('orderIndex: $orderIndex, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $SyncQueueTable extends SyncQueue
    with TableInfo<$SyncQueueTable, QueuedOperation> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SyncQueueTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    hasAutoIncrement: true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'PRIMARY KEY AUTOINCREMENT',
    ),
  );
  static const VerificationMeta _clientOperationIdMeta = const VerificationMeta(
    'clientOperationId',
  );
  @override
  late final GeneratedColumn<String> clientOperationId =
      GeneratedColumn<String>(
        'client_operation_id',
        aliasedName,
        false,
        type: DriftSqlType.string,
        requiredDuringInsert: true,
      );
  static const VerificationMeta _kindMeta = const VerificationMeta('kind');
  @override
  late final GeneratedColumn<String> kind = GeneratedColumn<String>(
    'kind',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('add_relative'),
  );
  static const VerificationMeta _methodMeta = const VerificationMeta('method');
  @override
  late final GeneratedColumn<String> method = GeneratedColumn<String>(
    'method',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _endpointMeta = const VerificationMeta(
    'endpoint',
  );
  @override
  late final GeneratedColumn<String> endpoint = GeneratedColumn<String>(
    'endpoint',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadMeta = const VerificationMeta(
    'payload',
  );
  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _subjectUlidMeta = const VerificationMeta(
    'subjectUlid',
  );
  @override
  late final GeneratedColumn<String> subjectUlid = GeneratedColumn<String>(
    'subject_ulid',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _subjectLabelMeta = const VerificationMeta(
    'subjectLabel',
  );
  @override
  late final GeneratedColumn<String> subjectLabel = GeneratedColumn<String>(
    'subject_label',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _lastErrorMeta = const VerificationMeta(
    'lastError',
  );
  @override
  late final GeneratedColumn<String> lastError = GeneratedColumn<String>(
    'last_error',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    clientOperationId,
    kind,
    method,
    endpoint,
    payload,
    subjectUlid,
    subjectLabel,
    status,
    attempts,
    lastError,
    createdAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sync_queue';
  @override
  VerificationContext validateIntegrity(
    Insertable<QueuedOperation> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('client_operation_id')) {
      context.handle(
        _clientOperationIdMeta,
        clientOperationId.isAcceptableOrUnknown(
          data['client_operation_id']!,
          _clientOperationIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_clientOperationIdMeta);
    }
    if (data.containsKey('kind')) {
      context.handle(
        _kindMeta,
        kind.isAcceptableOrUnknown(data['kind']!, _kindMeta),
      );
    }
    if (data.containsKey('method')) {
      context.handle(
        _methodMeta,
        method.isAcceptableOrUnknown(data['method']!, _methodMeta),
      );
    } else if (isInserting) {
      context.missing(_methodMeta);
    }
    if (data.containsKey('endpoint')) {
      context.handle(
        _endpointMeta,
        endpoint.isAcceptableOrUnknown(data['endpoint']!, _endpointMeta),
      );
    } else if (isInserting) {
      context.missing(_endpointMeta);
    }
    if (data.containsKey('payload')) {
      context.handle(
        _payloadMeta,
        payload.isAcceptableOrUnknown(data['payload']!, _payloadMeta),
      );
    } else if (isInserting) {
      context.missing(_payloadMeta);
    }
    if (data.containsKey('subject_ulid')) {
      context.handle(
        _subjectUlidMeta,
        subjectUlid.isAcceptableOrUnknown(
          data['subject_ulid']!,
          _subjectUlidMeta,
        ),
      );
    }
    if (data.containsKey('subject_label')) {
      context.handle(
        _subjectLabelMeta,
        subjectLabel.isAcceptableOrUnknown(
          data['subject_label']!,
          _subjectLabelMeta,
        ),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('last_error')) {
      context.handle(
        _lastErrorMeta,
        lastError.isAcceptableOrUnknown(data['last_error']!, _lastErrorMeta),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  QueuedOperation map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return QueuedOperation(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      clientOperationId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}client_operation_id'],
      )!,
      kind: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}kind'],
      )!,
      method: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}method'],
      )!,
      endpoint: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}endpoint'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      subjectUlid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}subject_ulid'],
      ),
      subjectLabel: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}subject_label'],
      ),
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      lastError: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_error'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
    );
  }

  @override
  $SyncQueueTable createAlias(String alias) {
    return $SyncQueueTable(attachedDatabase, alias);
  }
}

class QueuedOperation extends DataClass implements Insertable<QueuedOperation> {
  final int id;
  final String clientOperationId;

  /// Which typed operation this is — add_relative, add_event, edit_person.
  /// The batch endpoint is typed rather than a request forwarder, so the queue
  /// stores intent rather than a serialised HTTP call.
  final String kind;
  final String method;
  final String endpoint;
  final String payload;

  /// The person this operation is about, so the queue can be shown as
  /// "waiting: a father for Ngul Muan" rather than as a row of json.
  final String? subjectUlid;
  final String? subjectLabel;
  final String status;
  final int attempts;
  final String? lastError;
  final DateTime createdAt;
  const QueuedOperation({
    required this.id,
    required this.clientOperationId,
    required this.kind,
    required this.method,
    required this.endpoint,
    required this.payload,
    this.subjectUlid,
    this.subjectLabel,
    required this.status,
    required this.attempts,
    this.lastError,
    required this.createdAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['client_operation_id'] = Variable<String>(clientOperationId);
    map['kind'] = Variable<String>(kind);
    map['method'] = Variable<String>(method);
    map['endpoint'] = Variable<String>(endpoint);
    map['payload'] = Variable<String>(payload);
    if (!nullToAbsent || subjectUlid != null) {
      map['subject_ulid'] = Variable<String>(subjectUlid);
    }
    if (!nullToAbsent || subjectLabel != null) {
      map['subject_label'] = Variable<String>(subjectLabel);
    }
    map['status'] = Variable<String>(status);
    map['attempts'] = Variable<int>(attempts);
    if (!nullToAbsent || lastError != null) {
      map['last_error'] = Variable<String>(lastError);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    return map;
  }

  SyncQueueCompanion toCompanion(bool nullToAbsent) {
    return SyncQueueCompanion(
      id: Value(id),
      clientOperationId: Value(clientOperationId),
      kind: Value(kind),
      method: Value(method),
      endpoint: Value(endpoint),
      payload: Value(payload),
      subjectUlid: subjectUlid == null && nullToAbsent
          ? const Value.absent()
          : Value(subjectUlid),
      subjectLabel: subjectLabel == null && nullToAbsent
          ? const Value.absent()
          : Value(subjectLabel),
      status: Value(status),
      attempts: Value(attempts),
      lastError: lastError == null && nullToAbsent
          ? const Value.absent()
          : Value(lastError),
      createdAt: Value(createdAt),
    );
  }

  factory QueuedOperation.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return QueuedOperation(
      id: serializer.fromJson<int>(json['id']),
      clientOperationId: serializer.fromJson<String>(json['clientOperationId']),
      kind: serializer.fromJson<String>(json['kind']),
      method: serializer.fromJson<String>(json['method']),
      endpoint: serializer.fromJson<String>(json['endpoint']),
      payload: serializer.fromJson<String>(json['payload']),
      subjectUlid: serializer.fromJson<String?>(json['subjectUlid']),
      subjectLabel: serializer.fromJson<String?>(json['subjectLabel']),
      status: serializer.fromJson<String>(json['status']),
      attempts: serializer.fromJson<int>(json['attempts']),
      lastError: serializer.fromJson<String?>(json['lastError']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'clientOperationId': serializer.toJson<String>(clientOperationId),
      'kind': serializer.toJson<String>(kind),
      'method': serializer.toJson<String>(method),
      'endpoint': serializer.toJson<String>(endpoint),
      'payload': serializer.toJson<String>(payload),
      'subjectUlid': serializer.toJson<String?>(subjectUlid),
      'subjectLabel': serializer.toJson<String?>(subjectLabel),
      'status': serializer.toJson<String>(status),
      'attempts': serializer.toJson<int>(attempts),
      'lastError': serializer.toJson<String?>(lastError),
      'createdAt': serializer.toJson<DateTime>(createdAt),
    };
  }

  QueuedOperation copyWith({
    int? id,
    String? clientOperationId,
    String? kind,
    String? method,
    String? endpoint,
    String? payload,
    Value<String?> subjectUlid = const Value.absent(),
    Value<String?> subjectLabel = const Value.absent(),
    String? status,
    int? attempts,
    Value<String?> lastError = const Value.absent(),
    DateTime? createdAt,
  }) => QueuedOperation(
    id: id ?? this.id,
    clientOperationId: clientOperationId ?? this.clientOperationId,
    kind: kind ?? this.kind,
    method: method ?? this.method,
    endpoint: endpoint ?? this.endpoint,
    payload: payload ?? this.payload,
    subjectUlid: subjectUlid.present ? subjectUlid.value : this.subjectUlid,
    subjectLabel: subjectLabel.present ? subjectLabel.value : this.subjectLabel,
    status: status ?? this.status,
    attempts: attempts ?? this.attempts,
    lastError: lastError.present ? lastError.value : this.lastError,
    createdAt: createdAt ?? this.createdAt,
  );
  QueuedOperation copyWithCompanion(SyncQueueCompanion data) {
    return QueuedOperation(
      id: data.id.present ? data.id.value : this.id,
      clientOperationId: data.clientOperationId.present
          ? data.clientOperationId.value
          : this.clientOperationId,
      kind: data.kind.present ? data.kind.value : this.kind,
      method: data.method.present ? data.method.value : this.method,
      endpoint: data.endpoint.present ? data.endpoint.value : this.endpoint,
      payload: data.payload.present ? data.payload.value : this.payload,
      subjectUlid: data.subjectUlid.present
          ? data.subjectUlid.value
          : this.subjectUlid,
      subjectLabel: data.subjectLabel.present
          ? data.subjectLabel.value
          : this.subjectLabel,
      status: data.status.present ? data.status.value : this.status,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      lastError: data.lastError.present ? data.lastError.value : this.lastError,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('QueuedOperation(')
          ..write('id: $id, ')
          ..write('clientOperationId: $clientOperationId, ')
          ..write('kind: $kind, ')
          ..write('method: $method, ')
          ..write('endpoint: $endpoint, ')
          ..write('payload: $payload, ')
          ..write('subjectUlid: $subjectUlid, ')
          ..write('subjectLabel: $subjectLabel, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('lastError: $lastError, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    clientOperationId,
    kind,
    method,
    endpoint,
    payload,
    subjectUlid,
    subjectLabel,
    status,
    attempts,
    lastError,
    createdAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is QueuedOperation &&
          other.id == this.id &&
          other.clientOperationId == this.clientOperationId &&
          other.kind == this.kind &&
          other.method == this.method &&
          other.endpoint == this.endpoint &&
          other.payload == this.payload &&
          other.subjectUlid == this.subjectUlid &&
          other.subjectLabel == this.subjectLabel &&
          other.status == this.status &&
          other.attempts == this.attempts &&
          other.lastError == this.lastError &&
          other.createdAt == this.createdAt);
}

class SyncQueueCompanion extends UpdateCompanion<QueuedOperation> {
  final Value<int> id;
  final Value<String> clientOperationId;
  final Value<String> kind;
  final Value<String> method;
  final Value<String> endpoint;
  final Value<String> payload;
  final Value<String?> subjectUlid;
  final Value<String?> subjectLabel;
  final Value<String> status;
  final Value<int> attempts;
  final Value<String?> lastError;
  final Value<DateTime> createdAt;
  const SyncQueueCompanion({
    this.id = const Value.absent(),
    this.clientOperationId = const Value.absent(),
    this.kind = const Value.absent(),
    this.method = const Value.absent(),
    this.endpoint = const Value.absent(),
    this.payload = const Value.absent(),
    this.subjectUlid = const Value.absent(),
    this.subjectLabel = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.lastError = const Value.absent(),
    this.createdAt = const Value.absent(),
  });
  SyncQueueCompanion.insert({
    this.id = const Value.absent(),
    required String clientOperationId,
    this.kind = const Value.absent(),
    required String method,
    required String endpoint,
    required String payload,
    this.subjectUlid = const Value.absent(),
    this.subjectLabel = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.lastError = const Value.absent(),
    required DateTime createdAt,
  }) : clientOperationId = Value(clientOperationId),
       method = Value(method),
       endpoint = Value(endpoint),
       payload = Value(payload),
       createdAt = Value(createdAt);
  static Insertable<QueuedOperation> custom({
    Expression<int>? id,
    Expression<String>? clientOperationId,
    Expression<String>? kind,
    Expression<String>? method,
    Expression<String>? endpoint,
    Expression<String>? payload,
    Expression<String>? subjectUlid,
    Expression<String>? subjectLabel,
    Expression<String>? status,
    Expression<int>? attempts,
    Expression<String>? lastError,
    Expression<DateTime>? createdAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (clientOperationId != null) 'client_operation_id': clientOperationId,
      if (kind != null) 'kind': kind,
      if (method != null) 'method': method,
      if (endpoint != null) 'endpoint': endpoint,
      if (payload != null) 'payload': payload,
      if (subjectUlid != null) 'subject_ulid': subjectUlid,
      if (subjectLabel != null) 'subject_label': subjectLabel,
      if (status != null) 'status': status,
      if (attempts != null) 'attempts': attempts,
      if (lastError != null) 'last_error': lastError,
      if (createdAt != null) 'created_at': createdAt,
    });
  }

  SyncQueueCompanion copyWith({
    Value<int>? id,
    Value<String>? clientOperationId,
    Value<String>? kind,
    Value<String>? method,
    Value<String>? endpoint,
    Value<String>? payload,
    Value<String?>? subjectUlid,
    Value<String?>? subjectLabel,
    Value<String>? status,
    Value<int>? attempts,
    Value<String?>? lastError,
    Value<DateTime>? createdAt,
  }) {
    return SyncQueueCompanion(
      id: id ?? this.id,
      clientOperationId: clientOperationId ?? this.clientOperationId,
      kind: kind ?? this.kind,
      method: method ?? this.method,
      endpoint: endpoint ?? this.endpoint,
      payload: payload ?? this.payload,
      subjectUlid: subjectUlid ?? this.subjectUlid,
      subjectLabel: subjectLabel ?? this.subjectLabel,
      status: status ?? this.status,
      attempts: attempts ?? this.attempts,
      lastError: lastError ?? this.lastError,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (clientOperationId.present) {
      map['client_operation_id'] = Variable<String>(clientOperationId.value);
    }
    if (kind.present) {
      map['kind'] = Variable<String>(kind.value);
    }
    if (method.present) {
      map['method'] = Variable<String>(method.value);
    }
    if (endpoint.present) {
      map['endpoint'] = Variable<String>(endpoint.value);
    }
    if (payload.present) {
      map['payload'] = Variable<String>(payload.value);
    }
    if (subjectUlid.present) {
      map['subject_ulid'] = Variable<String>(subjectUlid.value);
    }
    if (subjectLabel.present) {
      map['subject_label'] = Variable<String>(subjectLabel.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (lastError.present) {
      map['last_error'] = Variable<String>(lastError.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SyncQueueCompanion(')
          ..write('id: $id, ')
          ..write('clientOperationId: $clientOperationId, ')
          ..write('kind: $kind, ')
          ..write('method: $method, ')
          ..write('endpoint: $endpoint, ')
          ..write('payload: $payload, ')
          ..write('subjectUlid: $subjectUlid, ')
          ..write('subjectLabel: $subjectLabel, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('lastError: $lastError, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $CachedPeopleTable cachedPeople = $CachedPeopleTable(this);
  late final $CachedEdgesTable cachedEdges = $CachedEdgesTable(this);
  late final $CachedUnionsTable cachedUnions = $CachedUnionsTable(this);
  late final $SyncQueueTable syncQueue = $SyncQueueTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    cachedPeople,
    cachedEdges,
    cachedUnions,
    syncQueue,
  ];
}

typedef $$CachedPeopleTableCreateCompanionBuilder =
    CachedPeopleCompanion Function({
      required String ulid,
      required String displayName,
      Value<String?> nativeName,
      Value<String> gender,
      Value<String?> birthDisplay,
      Value<int?> birthYear,
      Value<String?> deathDisplay,
      Value<int?> deathYear,
      Value<bool> isLiving,
      Value<bool> redacted,
      Value<String?> verificationStatus,
      Value<String?> photoUrl,
      Value<String?> generationLabel,
      required DateTime cachedAt,
      Value<int> rowid,
    });
typedef $$CachedPeopleTableUpdateCompanionBuilder =
    CachedPeopleCompanion Function({
      Value<String> ulid,
      Value<String> displayName,
      Value<String?> nativeName,
      Value<String> gender,
      Value<String?> birthDisplay,
      Value<int?> birthYear,
      Value<String?> deathDisplay,
      Value<int?> deathYear,
      Value<bool> isLiving,
      Value<bool> redacted,
      Value<String?> verificationStatus,
      Value<String?> photoUrl,
      Value<String?> generationLabel,
      Value<DateTime> cachedAt,
      Value<int> rowid,
    });

class $$CachedPeopleTableFilterComposer
    extends Composer<_$AppDatabase, $CachedPeopleTable> {
  $$CachedPeopleTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get ulid => $composableBuilder(
    column: $table.ulid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get displayName => $composableBuilder(
    column: $table.displayName,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nativeName => $composableBuilder(
    column: $table.nativeName,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get gender => $composableBuilder(
    column: $table.gender,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get birthDisplay => $composableBuilder(
    column: $table.birthDisplay,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get birthYear => $composableBuilder(
    column: $table.birthYear,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get deathDisplay => $composableBuilder(
    column: $table.deathDisplay,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get deathYear => $composableBuilder(
    column: $table.deathYear,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get isLiving => $composableBuilder(
    column: $table.isLiving,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get redacted => $composableBuilder(
    column: $table.redacted,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get verificationStatus => $composableBuilder(
    column: $table.verificationStatus,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get photoUrl => $composableBuilder(
    column: $table.photoUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get generationLabel => $composableBuilder(
    column: $table.generationLabel,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get cachedAt => $composableBuilder(
    column: $table.cachedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedPeopleTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedPeopleTable> {
  $$CachedPeopleTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get ulid => $composableBuilder(
    column: $table.ulid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get displayName => $composableBuilder(
    column: $table.displayName,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nativeName => $composableBuilder(
    column: $table.nativeName,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get gender => $composableBuilder(
    column: $table.gender,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get birthDisplay => $composableBuilder(
    column: $table.birthDisplay,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get birthYear => $composableBuilder(
    column: $table.birthYear,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get deathDisplay => $composableBuilder(
    column: $table.deathDisplay,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get deathYear => $composableBuilder(
    column: $table.deathYear,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get isLiving => $composableBuilder(
    column: $table.isLiving,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get redacted => $composableBuilder(
    column: $table.redacted,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get verificationStatus => $composableBuilder(
    column: $table.verificationStatus,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get photoUrl => $composableBuilder(
    column: $table.photoUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get generationLabel => $composableBuilder(
    column: $table.generationLabel,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get cachedAt => $composableBuilder(
    column: $table.cachedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedPeopleTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedPeopleTable> {
  $$CachedPeopleTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get ulid =>
      $composableBuilder(column: $table.ulid, builder: (column) => column);

  GeneratedColumn<String> get displayName => $composableBuilder(
    column: $table.displayName,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nativeName => $composableBuilder(
    column: $table.nativeName,
    builder: (column) => column,
  );

  GeneratedColumn<String> get gender =>
      $composableBuilder(column: $table.gender, builder: (column) => column);

  GeneratedColumn<String> get birthDisplay => $composableBuilder(
    column: $table.birthDisplay,
    builder: (column) => column,
  );

  GeneratedColumn<int> get birthYear =>
      $composableBuilder(column: $table.birthYear, builder: (column) => column);

  GeneratedColumn<String> get deathDisplay => $composableBuilder(
    column: $table.deathDisplay,
    builder: (column) => column,
  );

  GeneratedColumn<int> get deathYear =>
      $composableBuilder(column: $table.deathYear, builder: (column) => column);

  GeneratedColumn<bool> get isLiving =>
      $composableBuilder(column: $table.isLiving, builder: (column) => column);

  GeneratedColumn<bool> get redacted =>
      $composableBuilder(column: $table.redacted, builder: (column) => column);

  GeneratedColumn<String> get verificationStatus => $composableBuilder(
    column: $table.verificationStatus,
    builder: (column) => column,
  );

  GeneratedColumn<String> get photoUrl =>
      $composableBuilder(column: $table.photoUrl, builder: (column) => column);

  GeneratedColumn<String> get generationLabel => $composableBuilder(
    column: $table.generationLabel,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get cachedAt =>
      $composableBuilder(column: $table.cachedAt, builder: (column) => column);
}

class $$CachedPeopleTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedPeopleTable,
          CachedPerson,
          $$CachedPeopleTableFilterComposer,
          $$CachedPeopleTableOrderingComposer,
          $$CachedPeopleTableAnnotationComposer,
          $$CachedPeopleTableCreateCompanionBuilder,
          $$CachedPeopleTableUpdateCompanionBuilder,
          (
            CachedPerson,
            BaseReferences<_$AppDatabase, $CachedPeopleTable, CachedPerson>,
          ),
          CachedPerson,
          PrefetchHooks Function()
        > {
  $$CachedPeopleTableTableManager(_$AppDatabase db, $CachedPeopleTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedPeopleTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedPeopleTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedPeopleTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> ulid = const Value.absent(),
                Value<String> displayName = const Value.absent(),
                Value<String?> nativeName = const Value.absent(),
                Value<String> gender = const Value.absent(),
                Value<String?> birthDisplay = const Value.absent(),
                Value<int?> birthYear = const Value.absent(),
                Value<String?> deathDisplay = const Value.absent(),
                Value<int?> deathYear = const Value.absent(),
                Value<bool> isLiving = const Value.absent(),
                Value<bool> redacted = const Value.absent(),
                Value<String?> verificationStatus = const Value.absent(),
                Value<String?> photoUrl = const Value.absent(),
                Value<String?> generationLabel = const Value.absent(),
                Value<DateTime> cachedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedPeopleCompanion(
                ulid: ulid,
                displayName: displayName,
                nativeName: nativeName,
                gender: gender,
                birthDisplay: birthDisplay,
                birthYear: birthYear,
                deathDisplay: deathDisplay,
                deathYear: deathYear,
                isLiving: isLiving,
                redacted: redacted,
                verificationStatus: verificationStatus,
                photoUrl: photoUrl,
                generationLabel: generationLabel,
                cachedAt: cachedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String ulid,
                required String displayName,
                Value<String?> nativeName = const Value.absent(),
                Value<String> gender = const Value.absent(),
                Value<String?> birthDisplay = const Value.absent(),
                Value<int?> birthYear = const Value.absent(),
                Value<String?> deathDisplay = const Value.absent(),
                Value<int?> deathYear = const Value.absent(),
                Value<bool> isLiving = const Value.absent(),
                Value<bool> redacted = const Value.absent(),
                Value<String?> verificationStatus = const Value.absent(),
                Value<String?> photoUrl = const Value.absent(),
                Value<String?> generationLabel = const Value.absent(),
                required DateTime cachedAt,
                Value<int> rowid = const Value.absent(),
              }) => CachedPeopleCompanion.insert(
                ulid: ulid,
                displayName: displayName,
                nativeName: nativeName,
                gender: gender,
                birthDisplay: birthDisplay,
                birthYear: birthYear,
                deathDisplay: deathDisplay,
                deathYear: deathYear,
                isLiving: isLiving,
                redacted: redacted,
                verificationStatus: verificationStatus,
                photoUrl: photoUrl,
                generationLabel: generationLabel,
                cachedAt: cachedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedPeopleTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedPeopleTable,
      CachedPerson,
      $$CachedPeopleTableFilterComposer,
      $$CachedPeopleTableOrderingComposer,
      $$CachedPeopleTableAnnotationComposer,
      $$CachedPeopleTableCreateCompanionBuilder,
      $$CachedPeopleTableUpdateCompanionBuilder,
      (
        CachedPerson,
        BaseReferences<_$AppDatabase, $CachedPeopleTable, CachedPerson>,
      ),
      CachedPerson,
      PrefetchHooks Function()
    >;
typedef $$CachedEdgesTableCreateCompanionBuilder =
    CachedEdgesCompanion Function({
      required String parentUlid,
      required String childUlid,
      Value<String> kind,
      Value<int> rowid,
    });
typedef $$CachedEdgesTableUpdateCompanionBuilder =
    CachedEdgesCompanion Function({
      Value<String> parentUlid,
      Value<String> childUlid,
      Value<String> kind,
      Value<int> rowid,
    });

class $$CachedEdgesTableFilterComposer
    extends Composer<_$AppDatabase, $CachedEdgesTable> {
  $$CachedEdgesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get parentUlid => $composableBuilder(
    column: $table.parentUlid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get childUlid => $composableBuilder(
    column: $table.childUlid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedEdgesTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedEdgesTable> {
  $$CachedEdgesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get parentUlid => $composableBuilder(
    column: $table.parentUlid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get childUlid => $composableBuilder(
    column: $table.childUlid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedEdgesTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedEdgesTable> {
  $$CachedEdgesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get parentUlid => $composableBuilder(
    column: $table.parentUlid,
    builder: (column) => column,
  );

  GeneratedColumn<String> get childUlid =>
      $composableBuilder(column: $table.childUlid, builder: (column) => column);

  GeneratedColumn<String> get kind =>
      $composableBuilder(column: $table.kind, builder: (column) => column);
}

class $$CachedEdgesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedEdgesTable,
          CachedEdge,
          $$CachedEdgesTableFilterComposer,
          $$CachedEdgesTableOrderingComposer,
          $$CachedEdgesTableAnnotationComposer,
          $$CachedEdgesTableCreateCompanionBuilder,
          $$CachedEdgesTableUpdateCompanionBuilder,
          (
            CachedEdge,
            BaseReferences<_$AppDatabase, $CachedEdgesTable, CachedEdge>,
          ),
          CachedEdge,
          PrefetchHooks Function()
        > {
  $$CachedEdgesTableTableManager(_$AppDatabase db, $CachedEdgesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedEdgesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedEdgesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedEdgesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> parentUlid = const Value.absent(),
                Value<String> childUlid = const Value.absent(),
                Value<String> kind = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedEdgesCompanion(
                parentUlid: parentUlid,
                childUlid: childUlid,
                kind: kind,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String parentUlid,
                required String childUlid,
                Value<String> kind = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedEdgesCompanion.insert(
                parentUlid: parentUlid,
                childUlid: childUlid,
                kind: kind,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedEdgesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedEdgesTable,
      CachedEdge,
      $$CachedEdgesTableFilterComposer,
      $$CachedEdgesTableOrderingComposer,
      $$CachedEdgesTableAnnotationComposer,
      $$CachedEdgesTableCreateCompanionBuilder,
      $$CachedEdgesTableUpdateCompanionBuilder,
      (
        CachedEdge,
        BaseReferences<_$AppDatabase, $CachedEdgesTable, CachedEdge>,
      ),
      CachedEdge,
      PrefetchHooks Function()
    >;
typedef $$CachedUnionsTableCreateCompanionBuilder =
    CachedUnionsCompanion Function({
      required String ulid,
      required String partnerUlids,
      required String childUlids,
      Value<String> unionType,
      Value<int?> marriageYear,
      Value<int> orderIndex,
      Value<int> rowid,
    });
typedef $$CachedUnionsTableUpdateCompanionBuilder =
    CachedUnionsCompanion Function({
      Value<String> ulid,
      Value<String> partnerUlids,
      Value<String> childUlids,
      Value<String> unionType,
      Value<int?> marriageYear,
      Value<int> orderIndex,
      Value<int> rowid,
    });

class $$CachedUnionsTableFilterComposer
    extends Composer<_$AppDatabase, $CachedUnionsTable> {
  $$CachedUnionsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get ulid => $composableBuilder(
    column: $table.ulid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get partnerUlids => $composableBuilder(
    column: $table.partnerUlids,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get childUlids => $composableBuilder(
    column: $table.childUlids,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get unionType => $composableBuilder(
    column: $table.unionType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get marriageYear => $composableBuilder(
    column: $table.marriageYear,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get orderIndex => $composableBuilder(
    column: $table.orderIndex,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedUnionsTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedUnionsTable> {
  $$CachedUnionsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get ulid => $composableBuilder(
    column: $table.ulid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get partnerUlids => $composableBuilder(
    column: $table.partnerUlids,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get childUlids => $composableBuilder(
    column: $table.childUlids,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get unionType => $composableBuilder(
    column: $table.unionType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get marriageYear => $composableBuilder(
    column: $table.marriageYear,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get orderIndex => $composableBuilder(
    column: $table.orderIndex,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedUnionsTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedUnionsTable> {
  $$CachedUnionsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get ulid =>
      $composableBuilder(column: $table.ulid, builder: (column) => column);

  GeneratedColumn<String> get partnerUlids => $composableBuilder(
    column: $table.partnerUlids,
    builder: (column) => column,
  );

  GeneratedColumn<String> get childUlids => $composableBuilder(
    column: $table.childUlids,
    builder: (column) => column,
  );

  GeneratedColumn<String> get unionType =>
      $composableBuilder(column: $table.unionType, builder: (column) => column);

  GeneratedColumn<int> get marriageYear => $composableBuilder(
    column: $table.marriageYear,
    builder: (column) => column,
  );

  GeneratedColumn<int> get orderIndex => $composableBuilder(
    column: $table.orderIndex,
    builder: (column) => column,
  );
}

class $$CachedUnionsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedUnionsTable,
          CachedUnion,
          $$CachedUnionsTableFilterComposer,
          $$CachedUnionsTableOrderingComposer,
          $$CachedUnionsTableAnnotationComposer,
          $$CachedUnionsTableCreateCompanionBuilder,
          $$CachedUnionsTableUpdateCompanionBuilder,
          (
            CachedUnion,
            BaseReferences<_$AppDatabase, $CachedUnionsTable, CachedUnion>,
          ),
          CachedUnion,
          PrefetchHooks Function()
        > {
  $$CachedUnionsTableTableManager(_$AppDatabase db, $CachedUnionsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedUnionsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedUnionsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedUnionsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> ulid = const Value.absent(),
                Value<String> partnerUlids = const Value.absent(),
                Value<String> childUlids = const Value.absent(),
                Value<String> unionType = const Value.absent(),
                Value<int?> marriageYear = const Value.absent(),
                Value<int> orderIndex = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedUnionsCompanion(
                ulid: ulid,
                partnerUlids: partnerUlids,
                childUlids: childUlids,
                unionType: unionType,
                marriageYear: marriageYear,
                orderIndex: orderIndex,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String ulid,
                required String partnerUlids,
                required String childUlids,
                Value<String> unionType = const Value.absent(),
                Value<int?> marriageYear = const Value.absent(),
                Value<int> orderIndex = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedUnionsCompanion.insert(
                ulid: ulid,
                partnerUlids: partnerUlids,
                childUlids: childUlids,
                unionType: unionType,
                marriageYear: marriageYear,
                orderIndex: orderIndex,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedUnionsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedUnionsTable,
      CachedUnion,
      $$CachedUnionsTableFilterComposer,
      $$CachedUnionsTableOrderingComposer,
      $$CachedUnionsTableAnnotationComposer,
      $$CachedUnionsTableCreateCompanionBuilder,
      $$CachedUnionsTableUpdateCompanionBuilder,
      (
        CachedUnion,
        BaseReferences<_$AppDatabase, $CachedUnionsTable, CachedUnion>,
      ),
      CachedUnion,
      PrefetchHooks Function()
    >;
typedef $$SyncQueueTableCreateCompanionBuilder =
    SyncQueueCompanion Function({
      Value<int> id,
      required String clientOperationId,
      Value<String> kind,
      required String method,
      required String endpoint,
      required String payload,
      Value<String?> subjectUlid,
      Value<String?> subjectLabel,
      Value<String> status,
      Value<int> attempts,
      Value<String?> lastError,
      required DateTime createdAt,
    });
typedef $$SyncQueueTableUpdateCompanionBuilder =
    SyncQueueCompanion Function({
      Value<int> id,
      Value<String> clientOperationId,
      Value<String> kind,
      Value<String> method,
      Value<String> endpoint,
      Value<String> payload,
      Value<String?> subjectUlid,
      Value<String?> subjectLabel,
      Value<String> status,
      Value<int> attempts,
      Value<String?> lastError,
      Value<DateTime> createdAt,
    });

class $$SyncQueueTableFilterComposer
    extends Composer<_$AppDatabase, $SyncQueueTable> {
  $$SyncQueueTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get clientOperationId => $composableBuilder(
    column: $table.clientOperationId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get method => $composableBuilder(
    column: $table.method,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get subjectUlid => $composableBuilder(
    column: $table.subjectUlid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get subjectLabel => $composableBuilder(
    column: $table.subjectLabel,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SyncQueueTableOrderingComposer
    extends Composer<_$AppDatabase, $SyncQueueTable> {
  $$SyncQueueTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get clientOperationId => $composableBuilder(
    column: $table.clientOperationId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get method => $composableBuilder(
    column: $table.method,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get subjectUlid => $composableBuilder(
    column: $table.subjectUlid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get subjectLabel => $composableBuilder(
    column: $table.subjectLabel,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastError => $composableBuilder(
    column: $table.lastError,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SyncQueueTableAnnotationComposer
    extends Composer<_$AppDatabase, $SyncQueueTable> {
  $$SyncQueueTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get clientOperationId => $composableBuilder(
    column: $table.clientOperationId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get kind =>
      $composableBuilder(column: $table.kind, builder: (column) => column);

  GeneratedColumn<String> get method =>
      $composableBuilder(column: $table.method, builder: (column) => column);

  GeneratedColumn<String> get endpoint =>
      $composableBuilder(column: $table.endpoint, builder: (column) => column);

  GeneratedColumn<String> get payload =>
      $composableBuilder(column: $table.payload, builder: (column) => column);

  GeneratedColumn<String> get subjectUlid => $composableBuilder(
    column: $table.subjectUlid,
    builder: (column) => column,
  );

  GeneratedColumn<String> get subjectLabel => $composableBuilder(
    column: $table.subjectLabel,
    builder: (column) => column,
  );

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<String> get lastError =>
      $composableBuilder(column: $table.lastError, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);
}

class $$SyncQueueTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SyncQueueTable,
          QueuedOperation,
          $$SyncQueueTableFilterComposer,
          $$SyncQueueTableOrderingComposer,
          $$SyncQueueTableAnnotationComposer,
          $$SyncQueueTableCreateCompanionBuilder,
          $$SyncQueueTableUpdateCompanionBuilder,
          (
            QueuedOperation,
            BaseReferences<_$AppDatabase, $SyncQueueTable, QueuedOperation>,
          ),
          QueuedOperation,
          PrefetchHooks Function()
        > {
  $$SyncQueueTableTableManager(_$AppDatabase db, $SyncQueueTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SyncQueueTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SyncQueueTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SyncQueueTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> clientOperationId = const Value.absent(),
                Value<String> kind = const Value.absent(),
                Value<String> method = const Value.absent(),
                Value<String> endpoint = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<String?> subjectUlid = const Value.absent(),
                Value<String?> subjectLabel = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
              }) => SyncQueueCompanion(
                id: id,
                clientOperationId: clientOperationId,
                kind: kind,
                method: method,
                endpoint: endpoint,
                payload: payload,
                subjectUlid: subjectUlid,
                subjectLabel: subjectLabel,
                status: status,
                attempts: attempts,
                lastError: lastError,
                createdAt: createdAt,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String clientOperationId,
                Value<String> kind = const Value.absent(),
                required String method,
                required String endpoint,
                required String payload,
                Value<String?> subjectUlid = const Value.absent(),
                Value<String?> subjectLabel = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<String?> lastError = const Value.absent(),
                required DateTime createdAt,
              }) => SyncQueueCompanion.insert(
                id: id,
                clientOperationId: clientOperationId,
                kind: kind,
                method: method,
                endpoint: endpoint,
                payload: payload,
                subjectUlid: subjectUlid,
                subjectLabel: subjectLabel,
                status: status,
                attempts: attempts,
                lastError: lastError,
                createdAt: createdAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SyncQueueTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SyncQueueTable,
      QueuedOperation,
      $$SyncQueueTableFilterComposer,
      $$SyncQueueTableOrderingComposer,
      $$SyncQueueTableAnnotationComposer,
      $$SyncQueueTableCreateCompanionBuilder,
      $$SyncQueueTableUpdateCompanionBuilder,
      (
        QueuedOperation,
        BaseReferences<_$AppDatabase, $SyncQueueTable, QueuedOperation>,
      ),
      QueuedOperation,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$CachedPeopleTableTableManager get cachedPeople =>
      $$CachedPeopleTableTableManager(_db, _db.cachedPeople);
  $$CachedEdgesTableTableManager get cachedEdges =>
      $$CachedEdgesTableTableManager(_db, _db.cachedEdges);
  $$CachedUnionsTableTableManager get cachedUnions =>
      $$CachedUnionsTableTableManager(_db, _db.cachedUnions);
  $$SyncQueueTableTableManager get syncQueue =>
      $$SyncQueueTableTableManager(_db, _db.syncQueue);
}
