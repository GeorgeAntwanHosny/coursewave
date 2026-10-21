import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { useAuthStore } from "@/stores/auth-store";
import { toast } from "sonner";

export type Certificate = {
  id: number;
  certificate_number: string;
  course_title: string;
  course_slug: string | null;
  instructor_name: string;
  issued_at: string;
  issued_at_human: string;
  pdf_ready: boolean;
  verification_url: string;
};

export type CertificateVerification = {
  valid: boolean;
  certificate_number: string;
  student_name: string;
  course_title: string;
  instructor_name: string;
  issued_at_human: string;
};

// With responseType "blob", an error body arrives as a Blob, so read the message out of it.
async function messageFromError(err: any, fallback: string): Promise<string> {
  const data = err?.response?.data;
  try {
    if (data instanceof Blob) {
      const parsed = JSON.parse(await data.text());
      return parsed?.message ?? fallback;
    }
  } catch {
    // fall through to the generic path
  }
  return data?.message ?? fallback;
}

export function useMyCertificates() {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["certificates"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<Certificate[]>>("/certificates");
      return data.data ?? [];
    },
    enabled: !!token,
  });
}

export function useDownloadCertificate() {
  const queryClient = useQueryClient();
  return useMutation({
    // A plain link can't send the bearer token, so the PDF is fetched as a blob and saved.
    mutationFn: async (certificate: Certificate) => {
      const response = await apiClient.get(`/certificates/${certificate.id}/download`, { responseType: "blob" });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement("a");
      link.href = url;
      link.download = `CourseWave-Certificate-${certificate.certificate_number}.pdf`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    onSuccess: () => {
      toast.success("Certificate downloaded.");
      queryClient.invalidateQueries({ queryKey: ["certificates"] });
    },
    onError: async (err: any) => {
      toast.error(await messageFromError(err, "Couldn't download the certificate."));
      queryClient.invalidateQueries({ queryKey: ["certificates"] });
    },
  });
}

export function useVerifyCertificate(number: string) {
  return useQuery({
    queryKey: ["verify-certificate", number],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<CertificateVerification>>(
        `/certificates/verify/${encodeURIComponent(number)}`
      );
      return data.data;
    },
    enabled: !!number,
    retry: false, // a 404 means "not a valid certificate", not a transient failure
  });
}