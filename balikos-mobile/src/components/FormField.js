import { StyleSheet, Text, TextInput, View } from 'react-native';
import { colors, spacing } from '../theme';

export default function FormField({ label, value, onChangeText, placeholder, multiline = false, keyboardType, secureTextEntry, helperText }) {
  const preventCorrection = keyboardType === 'email-address' || keyboardType === 'url';
  return (
    <View style={styles.group}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        value={value || ''}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        multiline={multiline}
        keyboardType={keyboardType}
        secureTextEntry={secureTextEntry}
        autoComplete={secureTextEntry ? 'password' : undefined}
        textContentType={secureTextEntry ? 'password' : undefined}
        autoCapitalize={preventCorrection ? 'none' : 'sentences'}
        autoCorrect={!preventCorrection}
        style={[styles.input, multiline && styles.multiline]}
      />
      {helperText ? <Text style={styles.help}>{helperText}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  group: { marginBottom: spacing.md },
  label: { color: colors.goldLight, marginBottom: spacing.xs, fontWeight: '700' },
  input: {
    minHeight: 54,
    paddingHorizontal: spacing.md,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    color: colors.text,
    backgroundColor: colors.surface,
    fontSize: 15,
  },
  multiline: { minHeight: 104, paddingTop: spacing.md, textAlignVertical: 'top' },
  help: { color: colors.muted, fontSize: 12, marginTop: spacing.xs },
});
